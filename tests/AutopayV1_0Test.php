<?php

namespace Tests\Unit;

use BniApi\BniPhp\api\V1_0\Autopay;
use Faker;

/**
 * Class AutopayV1_0Test
 *
 * @package Tests\Unit
 * @coversDefaultClass \BniApi\BniPhp\api\Autopay
 */
class AutopayV1_0Test extends \Codeception\Test\Unit
{
    /**
     * @var Autopay
     */
    protected $autopay;

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

    const BANK_CARD_TOKEN = '7XAxQjVP1rvJm2rz2YcoBsBHlCXNIPReNI49PxrcOd9bUhhDnmjW016AxwzQq0V5mIhNSrbmx9YnRD7gDr23sKCUtUVbxjqnZj63SFcJQTfWobanRPNyTOx2q0B83ylU';

    /**
     * Set initial value
     */
    protected function _before()
    {
        // Get credentials from ../credentials.json
        $credentials = json_decode(file_get_contents(__DIR__ . '/../credentials.json.alpha-markonah'))->autopay;


        $this->autopay = new Autopay(
            $credentials->merchantID,
            $credentials->clientID,
            $credentials->clientSecret,
            $credentials->privateKey,
            Autopay::ENV_ALPHA
            // Autopay::ENV_BETA,
            // Autopay::ENV_PROD
        );
    }

    /**
     * Not implemented yet
     */
    protected function _after() {}

    /**
     * Main flow test, used for testing the "whole" process
     * from Account Binding, Debit, etc, to Account Unbinding.
     * 
     * @skip2
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
            $response->additionalInfo->referenceNo ?? '', // originalReferenceNo
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
            [
                'expiredOtp' => date('c', strtotime('+300 seconds')),
                'hasOtp' => 'yes'
            ], // additionalInfo
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
        $refundType = Autopay::REFUND_TYPE_FULL; // full or partial

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
            [
                'value'    => $amount,
                'currency' => 'IDR'
            ],
        );

        codecept_debug($refundStatusResponse);
        $this->assertEquals($refundStatusResponse->responseCode, self::RESP_CODE_DEBIT_STATUS);

        // Balance Inquiry ================================
        $sequence = '05';
        $partnerReferenceNo = $basePartnerReferenceNo . $sequence;

        $balanceInquiryResponse = $this->autopay->balanceInquiry(
            $partnerReferenceNo,
            $amount,
            $bankCardToken,
            $bankAccountNo
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
            [
                'expiredOtp' => date('c', strtotime('+300 seconds')),
                'hasOtp' => 'yes'
            ], // additionalInfo
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
            [
                'expiredOtp' => date('c', strtotime('+300 seconds')),
                'hasOtp' => 'yes'
            ], // additionalInfo
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
            [
                'expiredOtp' => date('c', strtotime('+300 seconds')),
                'hasOtp' => 'yes'
            ], // additionalInfo
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
