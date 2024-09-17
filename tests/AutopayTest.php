<?php
namespace Tests\Unit;

use BniApi\BniPhp\api\Autopay;

/**
 * Class AutopayTest
 *
 * @package Tests\Unit
 * @coversDefaultClass \BniApi\BniPhp\api\Autopay
 */
class AutopayTest extends \Codeception\Test\Unit
{
    /**
     * @var Autopay
     */
    private $autopay;

    // Success response code
    const RESP_CODE_ACCOUNT_BINDING   = '2000700';
    const RESP_CODE_ACCOUNT_UNBINDING = '2000900';
    const RESP_CODE_BALANCE_INQUIRY   = '2001100';
    const RESP_CODE_DEBIT             = '2005400';
    const RESP_CODE_DEBIT_REFUND      = '2005800';
    const RESP_CODE_DEBIT_STATUS      = '2005500';
    const RESP_CODE_LIMIT_INQUIRY     = '2000000';
    const RESP_CODE_OTP               = '2008100';
    const RESP_CODE_OTP_VERIFY        = '2000400';
    const RESP_CODE_SET_LIMIT         = '2000200';
    const RESP_CODE_SET_LIMIT_PENDING = '2020200';

    // Bank card token is like the "customer ID", returned from Account Binding
    const BANK_CARD_TOKEN = 'ozHO0Xb4voGihPiKv3sdimuI7Ye3gp4nc2jnUtUz30ZM4jQGLfFde3jLA5aGqNhMFTTxANckFIsYbzfUniildhALKbzOC65jwMgTqc2p4oEsJ2xrqTvnxrActFIiq7yI';

    /**
     * Set initial value
     */
    protected function _before()
    {
        // Get credentials from ../credentials.json
        $credentials = json_decode(file_get_contents(__DIR__ . '/../credentials.json'))->autopay;

        $this->autopay = new Autopay(
            $credentials->merchantID,
            $credentials->clientID,
            $credentials->clientSecret,
            $credentials->privateKey,
            Autopay::ENV_ALPHA
        );
    }

    /**
     * Not implemented yet
     */
    protected function _after(){}

    /**
     * experimental, change function to public
     * @skip
     */
    public function testGetSignatureToken()
    {
        $signatureToken = $this->autopay->getSignatureToken();

        codecept_debug($signatureToken);

        $this->assertNotNull($signatureToken);
    }

    /**
     * experimental
     * @skip
     */
    public function testGetToken()
    {
        $accessToken = $this->autopay->getToken();

        codecept_debug($accessToken);

        $this->assertNotNull($accessToken);
    }

    public function testAccountBinding()
    {
        $partnerReferenceNo = '123456989009876544020';
        $bankAccountNo = '1234555557';
        $bankCardNo = '92345678902998';
        $limit = 250000.00;
        $email = 'burhanaji2@gmail.com';
        $custIdMerchant = '92345678902788';

        $response = $this->autopay->accountBinding(
            $partnerReferenceNo,
            $bankAccountNo,
            $bankCardNo,
            $limit,
            $email,
            $custIdMerchant
        );

        codecept_debug($response);
        $this->assertEquals($response->responseCode, self::RESP_CODE_ACCOUNT_BINDING);
    }

    public function testVerifyOtp()
    {
        $originalPartnerReferenceNo = '123456989009876544020';
        $originalReferenceNo        = '6816517742983299400125440638471568809774524587798161498336361836';
        $chargeToken                = '39ePbCMEQzAhpKR0bMdCCKU4SKt5Fm';
        $otp                        = '675903';

        $response = $this->autopay->verifyOtp(
            $originalPartnerReferenceNo,
            $originalReferenceNo,
            $chargeToken,
            $otp,
        );

        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_OTP_VERIFY);
    }

    public function testOtp()
    {
        $partnerReferenceNo = '2024102899929999999233';
        $journeyID          = '1234568810198000019';
        $bankCardToken      = self::BANK_CARD_TOKEN;
        $otpReasonCode    = Autopay::OTP_CODE_DIRECT_DEBIT;
        $additionalInfo   = [
            'expiredOtp' => date('c', strtotime('+300 seconds')),
        ];

        // generate external store ID from random number
        $externalStoreId = rand(10, 100000000) . time();

        $this->autopay->setHeader('externalID', $journeyID);

        $response = $this->autopay->otp(
            $partnerReferenceNo,
            $journeyID,
            $bankCardToken,
            $otpReasonCode,
            $additionalInfo,
            $externalStoreId,
        );

        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_OTP);
    }

    public function testDebit()
    {
        $partnerReferenceNo = '2023102899999999999991';
        $bankCardToken      = self::BANK_CARD_TOKEN;
        $chargeToken = 'edh5OJ3b3nRZfvIgrhyEY0thvXy1XB';
        $otp         = '';
        $amount      = [
            'value'    => '2.00',
            'currency' => 'IDR'
        ];
        $remark      = 'remark';
        
        $response = $this->autopay->debit(
            $partnerReferenceNo,
            $bankCardToken,
            $chargeToken,
            $otp,
            $amount,
            $remark
        );

        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_DEBIT);
    }

    public function testDebitStatus()
    {
        $originalPartnerReferenceNo = '2023102899999999999991';
        $transactionDate = '2024-09-13T17:55:39+07:00';
        $serviceCode       = Autopay::SERVICECODE_DEBIT;
        $amount            = [
            'value'    => 2.00,
            'currency' => 'IDR'
        ];
        
        $response = $this->autopay->debitStatus(
            $originalPartnerReferenceNo,
            $transactionDate,
            $serviceCode,
            $amount
        );

        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_DEBIT_STATUS);
    }

    public function testDebitRefund()
    {
        $originalPartnerReferenceNo = '2023102899999999999991';
        $partnerRefundNo            = '2023102899999999991992';
        $refundAmount               = [
            'value'    => 2.00,
            'currency' => 'IDR'
        ];
        $reason     = 'Complaint from customer';
        $refundType = Autopay::REFUND_TYPE_FULL;// full or partial
        
        $response = $this->autopay->debitRefund(
            $originalPartnerReferenceNo,
            $partnerRefundNo,
            $refundAmount,
            $reason,
            $refundType
        );
        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_DEBIT_REFUND);
    }

    public function testBalanceInquiry()
    {
        $partnerReferenceNo = '20231028999999929988893';
        $accountNo = '1234555557';
        $amount = 1000.00;
        $bankCardToken = self::BANK_CARD_TOKEN;

        $response = $this->autopay->balanceInquiry(
            $partnerReferenceNo,
            $accountNo,
            $amount,
            $bankCardToken
        );

        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_BALANCE_INQUIRY);
    }

    public function testLimitInquiry()
    {
        $partnerReferenceNo = '2020102900000000200003';
        $bankCardToken = self::BANK_CARD_TOKEN;
        $accountNo          = '1234555557';
        $amount             = 200000.00;
        
        $response = $this->autopay->limitInquiry(
            $accountNo,
            $partnerReferenceNo,
            $bankCardToken,
            $amount
        );

        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_LIMIT_INQUIRY);
    }

    public function testOtpSetLimit()
    {
        $partnerReferenceNo = '2024102899929999999236';
        $journeyID          = '1234568810198001022';
        $bankCardToken      = self::BANK_CARD_TOKEN;
        $otpReasonCode    = Autopay::OTP_CODE_CARD_REGISTRATION_SET_LIMIT;
        $additionalInfo   = [
            'expiredOtp' => date('c', strtotime('+300 seconds')),
        ];

        // generate external store ID from random number
        $externalStoreId = rand(10, 100000000) . time();

        $this->autopay->setHeader('externalID', $journeyID);

        $response = $this->autopay->otp(
            $partnerReferenceNo,
            $journeyID,
            $bankCardToken,
            $otpReasonCode,
            $additionalInfo,
            $externalStoreId,
        );

        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_OTP);
    }

    public function testSetLimit()
    {
        $partnerReferenceNo = '2023102899929999999968';
        $bankCardToken = self::BANK_CARD_TOKEN;
        $limit              = 1000000.00;
        $otp                = '881873';
        $chargeToken        = 'UkLo4dS8wHHfOmwX2MOz5Se3gw4fJn';

        $response = $this->autopay->setLimit(
            $partnerReferenceNo,
            $bankCardToken,
            $limit,
            $chargeToken,
            $otp
        );

        codecept_debug($response);

        $this->assertContains(
            $response->responseCode, 
            [
                self::RESP_CODE_SET_LIMIT, // applied immediately
                self::RESP_CODE_SET_LIMIT_PENDING // the changes will be applied at 00:00 / next day
            ]
        );
    }

    public function testOtpAccountUnbinding()
    {
        $partnerReferenceNo = '2024103899929999999233';
        $journeyID          = '1234568810198040000';
        $bankCardToken      = self::BANK_CARD_TOKEN;
        $otpReasonCode    = Autopay::OTP_CODE_ACCOUNT_UNBINDING;
        $additionalInfo   = [
            'expiredOtp' => date('c', strtotime('+300 seconds')),
        ];

        // generate external store ID from random number
        $externalStoreId = rand(10, 100000000) . time();

        $this->autopay->setHeader('externalID', $journeyID);

        $response = $this->autopay->otp(
            $partnerReferenceNo,
            $journeyID,
            $bankCardToken,
            $otpReasonCode,
            $additionalInfo,
            $externalStoreId,
        );

        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_OTP);
    }

    public function testAccountUnbinding()
    {
        $partnerReferenceNo = '2023102899929999999969';
        $bankCardToken      = self::BANK_CARD_TOKEN;
        $chargeToken        = 'V2P7VpYzNlgf33YSAi9vWS0USHK989';
        $otp                = '250286';
        $custIdMerchant     = '12313213131';
        
        $response = $this->autopay->accountUnbinding(
            $partnerReferenceNo,
            $bankCardToken,
            $chargeToken,
            $otp,
            $custIdMerchant
        );

        codecept_debug($response);

        $this->assertEquals($response->responseCode, self::RESP_CODE_ACCOUNT_UNBINDING);
    }
}
