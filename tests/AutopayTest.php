<?php
namespace Tests\Unit;

use BniApi\BniPhp\api\Autopay;
use Faker;

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
    const BANK_CARD_TOKEN = '6wJ9jGYoh43jCpmUeiOiViEVCQtp6MX4zeLn2h4YfPrkEIsFhBrgCi56zJe1sEF1AZW1TKbs1ddorhkyE77gi27rotwMSXvRFKv7OPTnoxiRWDFzMaesoAjvWTa0qB9h';

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
        $partnerReferenceNo = '2024102899929999999234';
        $journeyID          = '1234568810198000020';
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
        $partnerReferenceNo = '3793473647503';
        $bankCardToken      = '71VDki7JGwRhM0ILh1Tt7cDvTGYf6SLURX0p4kZToQAy800Yduw0M149wfjnfYwabmUtlsOx5Hy0o3kdpZ6awmaOiDQlME2R5uTUH9QWT9vBRJkdB89gYDDCmhmmggD7';
        $chargeToken = 'YrB9V11JNAu3WO0aiw7pYsRVmdb340';
        $otp         = '413763';
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
        $bankCardToken      = self::BANK_CARD_TOKEN;
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

    /**
     * Main flow test, used for testing the "whole" process
     * from Account Binding, Debit, etc, to Account Unbinding.
     * 
     * @skip
     */
    public function testMainFlow()
    {
        $faker = Faker\Factory::create();
        
        // Account Binding ====================================
        $sequence = '01';
        $basePartnerReferenceNo = $faker->numerify('###############');
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;
        $bankAccountNo      = $faker->numerify('##########');
        $bankCardNo         = $faker->randomNumber(4, true);
        $limit              = 250000.00;
        $email              = $faker->email();
        $custIdMerchant     = $faker->numerify('##########');
        
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
        
        $otpCode = readline('Please input the OTP before run testVerifyOtp: ');

        // Verify OTP ========================================
        $verifyOtpResponse = $this->autopay->verifyOtp(
            $partnerReferenceNo, // originalPartnerReferenceNo
            $response->referenceNo ?? '', // originalReferenceNo
            $response->additionalInfo->chargeToken ?? '',
            (string) $otpCode,
        );

        codecept_debug($verifyOtpResponse);
        $this->assertEquals($verifyOtpResponse->responseCode, self::RESP_CODE_OTP_VERIFY);

        // save bank card token for the following process
        $bankCardToken = $verifyOtpResponse->bankCardToken ?? '';

        sleep(1);

        // OTP Before Debit =================================
        $sequence = '02';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;
        $journeyID = $faker->numerify('###################');
        $otpReasonCode = Autopay::OTP_CODE_DIRECT_DEBIT;
        
        $this->autopay->setHeader('externalID', $journeyID);
        $externalStoreId = $faker->numerify('#############');
        sleep(1);

        $otpResponse = $this->autopay->otp(
            $partnerReferenceNo,
            $journeyID,
            $bankCardToken,
            $otpReasonCode,
            ['expiredOtp' => date('c', strtotime('+300 seconds'))],// additionalInfo
            $externalStoreId
        );

        codecept_debug($otpResponse);
        $this->assertEquals($otpResponse->responseCode, self::RESP_CODE_OTP);

        sleep(1);

        // Debit ============================================
        $sequence = '03';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;
        $chargeToken = $otpResponse->chargeToken ?? '';
        
        $otpDebitCode = readline('Please input the OTP before run testDebit: ');
        $amount = readline('Amount: ');
        if (empty($amount)) {
            $amount = 2.00;
        }
        $remark = 'remark';

        $this->autopay->setHeader('externalID', null); // clear the header

        $debitResponse = $this->autopay->debit(
            $partnerReferenceNo,
            $bankCardToken,
            $chargeToken,
            $otpDebitCode,
            [
                'value'    => $amount,
                'currency' => 'IDR'
            ],
            (string) $remark
        );

        codecept_debug($debitResponse);
        $this->assertEquals($debitResponse->responseCode, self::RESP_CODE_DEBIT);

        sleep(1);

        // Debit Status =====================================
        $paymentDate = $debitResponse->additionalInfo->paymentDate ?? '';

        $debitStatusResponse = $this->autopay->debitStatus(
            $partnerReferenceNo, // originalPartnerReferenceNo == debit partnerReferenceNo
            $paymentDate,
            Autopay::SERVICECODE_DEBIT,
            [
                'value'    => $amount,
                'currency' => 'IDR'
            ]
        );

        codecept_debug($debitStatusResponse);
        $this->assertEquals($debitStatusResponse->responseCode, self::RESP_CODE_DEBIT_STATUS);

        sleep(1);

        // Debit Refund Full ===============================
        $sequence = '04';
        $partnerRefundNo = $basePartnerReferenceNo . $sequence;
        $refundAmount = [
            'value'    => $amount,
            'currency' => 'IDR'
        ];
        $reason = 'Complaint from customer';
        $refundType = Autopay::REFUND_TYPE_FULL;// full or partial

        $refundResponse = $this->autopay->debitRefund(
            $partnerReferenceNo, // originalPartnerReferenceNo == debit partnerReferenceNo
            $partnerRefundNo,
            $refundAmount,
            $reason,
            $refundType
        );

        codecept_debug($refundResponse);
        $this->assertEquals($refundResponse->responseCode, self::RESP_CODE_DEBIT_REFUND);

        sleep(1);

        // Debit Refund Status ==============================
        // Hit debit status but with refund service code (58)
        $refundStatusResponse = $this->autopay->debitStatus(
            $partnerReferenceNo, // originalPartnerReferenceNo == debit partnerReferenceNo
            $paymentDate, // same as response from debit
            Autopay::SERVICECODE_REFUND,
            $refundAmount
        );

        codecept_debug($refundStatusResponse);
        $this->assertEquals($refundStatusResponse->responseCode, self::RESP_CODE_DEBIT_STATUS);

        // Balance Inquiry ================================
        $sequence = '05';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;

        $balanceInquiryResponse = $this->autopay->balanceInquiry(
            $partnerReferenceNo,
            $bankAccountNo,
            $amount,
            $bankCardToken
        );

        codecept_debug($balanceInquiryResponse);
        $this->assertEquals($balanceInquiryResponse->responseCode, self::RESP_CODE_BALANCE_INQUIRY);

        sleep(1);

        // Limit Inquiry ==================================
        $sequence = '06';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;

        $limitInquiryAmount = 200000.00;

        $limitInquiryResponse = $this->autopay->limitInquiry(
            $bankAccountNo,
            $partnerReferenceNo,
            $bankCardToken,
            $limitInquiryAmount
        );

        codecept_debug($limitInquiryResponse);
        $this->assertEquals($limitInquiryResponse->responseCode, self::RESP_CODE_LIMIT_INQUIRY);
        
        sleep(1);

        // OTP Set Limit UP ==================================
        $sequence = '07';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;
        $journeyID = $faker->numerify('###########');
        $otpReasonCode = Autopay::OTP_CODE_CARD_REGISTRATION_SET_LIMIT;
        $this->autopay->setHeader('externalID', $journeyID);
        $externalStoreId = $faker->numerify('#############');

        $otpResponse = $this->autopay->otp(
            $partnerReferenceNo,
            $journeyID,
            $bankCardToken,
            $otpReasonCode,
            ['expiredOtp' => date('c', strtotime('+300 seconds'))],// additionalInfo
            $externalStoreId,
        );

        codecept_debug($otpResponse);
        $this->assertEquals($otpResponse->responseCode, self::RESP_CODE_OTP);

        sleep(1);

        // Set Limit - UP  ======================================
        $sequence = '08';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;

        $otpCode = readline('Please input the OTP before run testSetLimit: ');
        $newLimit = 500000.00;
        $chargeToken = $otpResponse->chargeToken ?? '';

        // will cause the response to be "Conflict" if the header is not cleared
        $this->autopay->setHeader('externalID', null);

        $setLimitResponse = $this->autopay->setLimit(
            $partnerReferenceNo,
            $bankCardToken,
            $newLimit,
            $chargeToken,
            $otpCode
        );
        
        codecept_debug($setLimitResponse);
        $this->assertEquals($setLimitResponse->responseCode, self::RESP_CODE_SET_LIMIT);

        sleep(1);

        // OTP Set Limit DOWN ===============================
        $sequence = '09';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;
        $journeyID = $faker->numerify('###########');
        $otpReasonCode = Autopay::OTP_CODE_CARD_REGISTRATION_SET_LIMIT;
        $this->autopay->setHeader('externalID', $journeyID);
        $externalStoreId = $faker->numerify('#############');

        $otpResponse = $this->autopay->otp(
            $partnerReferenceNo,
            $journeyID,
            $bankCardToken,
            $otpReasonCode,
            ['expiredOtp' => date('c', strtotime('+300 seconds'))],// additionalInfo
            $externalStoreId,
        );

        codecept_debug($otpResponse);
        $this->assertEquals($otpResponse->responseCode, self::RESP_CODE_OTP);

        // Set Limit - DOWN ====================================
        $sequence = '10';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;

        $otpCode = readline('Please input the OTP before run testSetLimit: ');
        $newLimit = 300000.00;
        $chargeToken = $otpResponse->chargeToken ?? '';

        // will cause the response to be "Conflict" if the header is not cleared
        $this->autopay->setHeader('externalID', null);

        $setLimitResponse = $this->autopay->setLimit(
            $partnerReferenceNo,
            $bankCardToken,
            $newLimit,
            $chargeToken,
            $otpCode
        );
        
        codecept_debug($setLimitResponse);
        $this->assertEquals($setLimitResponse->responseCode, self::RESP_CODE_SET_LIMIT_PENDING);

        sleep(1);

        // OTP Account Unbinding ===========================
        $sequence = '11';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;
        $journeyID = $faker->numerify('###########');
        $otpReasonCode = Autopay::OTP_CODE_ACCOUNT_UNBINDING;
        $this->autopay->setHeader('externalID', $journeyID);
        
        $externalStoreId = $faker->numerify('#############');

        $otpResponse = $this->autopay->otp(
            $partnerReferenceNo,
            $journeyID,
            $bankCardToken,
            $otpReasonCode,
            ['expiredOtp' => date('c', strtotime('+300 seconds'))],// additionalInfo
            $externalStoreId,
        );

        codecept_debug($otpResponse);
        $this->assertEquals($otpResponse->responseCode, self::RESP_CODE_OTP);

        sleep(1);

        // Account Unbinding ================================
        $sequence = '12';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;
        $chargeToken = $otpResponse->chargeToken ?? '';

        $otpCode = readline('Please input the OTP before run testAccountUnbinding: ');

        // clear the header
        $this->autopay->setHeader('externalID', null);

        $accountUnbindingResponse = $this->autopay->accountUnbinding(
            $partnerReferenceNo,
            $bankCardToken,
            $chargeToken,
            $otpCode,
            $custIdMerchant // same value as accountBinding
        );

        codecept_debug($accountUnbindingResponse);
        $this->assertEquals($accountUnbindingResponse->responseCode, self::RESP_CODE_ACCOUNT_UNBINDING);

        codecept_debug("
Main flow test completed. Thank you, and here is your potato...
       ___
    .-'   `'.
  .'          \
 /             \
|               |
|               |
 \             /
  `._       _.'
     `''---''
        ");
    }
}