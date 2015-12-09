<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\AlphaBank;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Event\RequestEvents;

class AlphaBankTest extends \AktiveMerchant\TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('alphabank');

        $this->gateway = new AlphaBank($options);
        $this->amount = 0.09;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4000000000000002",
                "month" => "01",
                "year" => "17",
                "verification_value" => "123"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Test Transaction',
            'cavv' => null,
            'eci' => null,
            'xid' => 'MDkzNjY1NzE3NzI4MzMxMjQyMDE=',
            'enrollment_status' => 'N',
            'authentication_status' => null,
            'country' => 'US',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    public function testPurchase()
    {
        $this->mock_request($this->success_purchase_repsponse());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testAuthorize()
    {
        $this->mock_request($this->success_authorize_response());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testCredit()
    {
        $this->mock_request($this->success_credit_response());

        $this->options['payment_method'] = 'visa';
        $this->options['order_id'] = '1369981694782';
        $response = $this->gateway->credit(
            $this->amount,
            'xxxxxxxxxxxxxxxx',
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testVoid()
    {
        $this->mock_request($this->success_void_response());

        $this->options['payment_method'] = 'visa';
        $this->options['order_id'] = 'REF9355783865';
        $this->options['money'] = 0.09;
        $response = $this->gateway->void(
            $this->creditcard->number,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testStatus()
    {
        $this->mock_request($this->success_status_response());

        $response = $this->gateway->status('24227111');
    }

    public function testErrorHandling()
    {
        $this->mock_request($this->error_response());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        $this->assert_failure($response);
        $this->assertTrue($response->test());
    }

    public function testDuplicateOrder()
    {
        $this->mock_request($this->error_duplicate_order_response());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        $this->assert_failure($response);
        $this->assertTrue($response->test());
    }

    public function testUnsupportedCard()
    {
        $this->mock_request($this->error_support_card_response());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        $this->assert_failure($response);
        $this->assertTrue($response->test());
    }

    private function success_purchase_repsponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="00b64732ba90dd5aad0eda4568deae90"><SaleResponse><OrderId>REF9355783865</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>CAPTURED</Status><TxId>24227051</TxId><PaymentRef>133541</PaymentRef><RiskScore>0</RiskScore><Description>OK, CAPTURED response code 00</Description></SaleResponse></Message><Digest>rDWmTquVdqWv1vaAlucstv/IaOc=</Digest></VPOS>';
    }

    private function error_response()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="1434105759548"><ErrorMessage><ErrorCode>SE</ErrorCode><Description>Unspecified Exception. Errror id: 1434105759548</Description></ErrorMessage></Message></VPOS>';
    }

    private function error_duplicate_order_response()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="aa145b481ad789d89cec4efb2c1bff52"><SaleResponse><OrderId>REF123</OrderId><TxId>0</TxId><ErrorCode>I0</ErrorCode><Description>[Invalid order id REF123 (duplicate)]</Description></SaleResponse></Message><Digest>9ys+S3YA+KrKnUEd8I7yMSxt878=</Digest></VPOS>';
    }

    private function error_support_card_response()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="a0ffa4eaede5d79c8011215b884642e8"><SaleResponse><OrderId>REF7947573095</OrderId><OrderAmount>1.25</OrderAmount><Currency>EUR</Currency><PaymentTotal>1.25</PaymentTotal><Status>REFUSED</Status><TxId>1540891</TxId><PaymentRef>406213</PaymentRef><RiskScore>0</RiskScore><Description>Refused, REFUSED response code T3</Description></SaleResponse></Message><Digest>YGYEQFKWkjMZrfFXkNQUG9SKSck=</Digest></VPOS>';
    }

    private function success_authorize_response()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="aabdc078302f8105fce4c3f30fbc6374"><AuthorisationResponse><OrderId>REF1985947185</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>AUTHORIZED</Status><TxId>1541201</TxId><PaymentRef>750140</PaymentRef><RiskScore>0</RiskScore><Description>OK, AUTHORIZED response code 00</Description></AuthorisationResponse></Message><Digest>6T3WzbFkeubZwC3ogtjmLufmau0=</Digest></VPOS>';
    }

    private function success_credit_response()
    {
       return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="7cf4522940c8f672a0f3499792c476e2"><RefundResponse><OrderId>1369981694782</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>CAPTURED</Status><TxId>1545651</TxId><Description>OK, CAPTURED response code 00</Description></RefundResponse></Message><Digest>HVpuSrccNqrcMSfuXTQwjetUjZ8=</Digest></VPOS>';
    }

    private function success_void_response()
    {
       return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="e168db59b2acf9c4d66a1db0c57b64e7"><CancelResponse><OrderId>REF9355783865</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>AUTHORIZED</Status><TxId>24227111</TxId><PaymentRef>133541</PaymentRef><Description>OK, AUTHORIZED response code 00</Description></CancelResponse></Message><Digest>zHmmXsy9sJOH7INceSNDNJKroi0=</Digest></VPOS>';
    }

    private function success_status_response()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="7b00c7380e025722cf5b61b12feb49d0" timeStamp="2015-12-09T16:03:51.332+02:00"><StatusResponse><TransactionDetails><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>AUTHORIZED</Status><TxId>24227111</TxId><PaymentRef>133541</PaymentRef><Description>OK, AUTHORIZED response code 00</Description><TxType>VOID</TxType><TxDate>2015-12-09T15:51:50.206+02:00</TxDate><TxStarted>2015-12-09T15:51:50.158+02:00</TxStarted><TxCompleted>2015-12-09T15:51:50.721+02:00</TxCompleted><PaymentMethod>visa</PaymentMethod><Attribute name="MERCHANT NO">0022000230</Attribute><Attribute name="USER IP">77.69.3.98</Attribute><Attribute name="CHANNEL">XML API</Attribute><Attribute name="SETTLEMENT STATUS">NA</Attribute><Attribute name="BATCH NO">1</Attribute><Attribute name="ISO response code">00</Attribute><Attribute name="ORDER DESCRIPTION"/><Attribute name="CARD MASK PAN">4000########0002</Attribute><Attribute name="ECOM-FLG"> </Attribute><Attribute name="BONUS PARTICIPATION">No</Attribute></TransactionDetails></StatusResponse></Message><Digest>cwDXoAgB9pPb4jRF1NJP8aEpCN0=</Digest></VPOS>';
    }
}
