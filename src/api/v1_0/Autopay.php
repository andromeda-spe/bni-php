<?php

namespace BniApi\BniPhp\api\V1_0;

use BniApi\BniPhp\api\Autopay as BaseAutopay;
use BniApi\BniPhp\utils\Constant;
use BniApi\BniPhp\utils\Response;

class Autopay extends BaseAutopay
{
    protected $apiVersion = '/v1.0';

    /**
     * @inheritDoc
     */
    public function accountBinding(
        string $partnerReferenceNo,
        string $bankAccountNo,
        string $bankCardNo,
        float $limit = 1,
        string $email = '',
        string $custIdMerchant = ''
    ) {
        // validate limit
        if ($limit <= 0) {
            throw new \InvalidArgumentException('limit should be greater than 0');
        }

        $limit = $this->utils->formatAmount($limit);

        $token = $this->getToken();
        $timeStamp = $this->utils->getTimeStamp();

        $data = [
            'partnerReferenceNo' => $partnerReferenceNo,
            'merchantId' => $this->merchantID,
            'additionalData' => [
                'bankAccountNo' => $bankAccountNo,
                'bankCardNo'    => $bankCardNo,
                'limit'         => (string) $limit,
                'email'         => $email
            ],
            'additionalInfo' => [
                'custIdMerchant' => $custIdMerchant
            ]
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_ACCOUNT_BINDING, $token, $data, $timeStamp);
        return Response::autopay($response);
    }

    /**
     * @inheritDoc
     */
    public function accountUnbinding(
        string $partnerReferenceNo,
        string $bankCardToken,
        string $chargeToken,
        string $otp,
        string $custIdMerchant = ''
    ) {
        $token = $this->getToken();
        $timeStamp = $this->utils->getTimeStamp();

        $data = [
            'partnerReferenceNo' => $partnerReferenceNo,
            'merchantId'         => $this->merchantID,
            'chargeToken'        => $chargeToken,
            'otp'                => $otp,
            'bankCardToken'      => $bankCardToken,
            'additionalInfo' => [
                'custIdMerchant' => $custIdMerchant,
            ]
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_ACCOUNT_UNBINDING, $token, $data, $timeStamp);
        return Response::autopay($response);
    }

    /**
     * Balance Inquiry (Check if the balance of a bank account is sufficient for a transaction)
     *
     * @param string $partnerReferenceNo unique identifier string (max 64 chars)
     * @param float $amount transaction amount in float
     * @param string $bankCardToken unique customer identifier, generated when hit account binding API
     * @param string $accountNo bank account number
     * @return Object
     */
    public function balanceInquiry(
        string $partnerReferenceNo,
        float $amount,
        string $bankCardToken,
        string $accountNo = '',
    ) {
        $timeStamp = $this->utils->getTimeStamp();
        $token = $this->getToken();

        $amount = $this->utils->formatAmount($amount);

        $data = [
            'partnerReferenceNo' => $partnerReferenceNo,
            'accountNo'          => $accountNo,
            'additionalInfo' => [
                'amount' => (string) $amount,
            ],
            'bankCardToken' => $bankCardToken
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_BALANCE_INQUIRY, $token, $data, $timeStamp);
        return Response::autopay($response);
    }

    /**
     * @inheritDoc
     */
    public function debit(
        string $partnerReferenceNo,
        string $bankCardToken,
        string $chargeToken,
        string $otp = '',
        array $amount = ['value' => 0.00, 'currency' => 'IDR'],
        string $remark = ''
    ) {
        $token = $this->getToken();
        $timeStamp = $this->utils->getTimeStamp();

        $data = [
            'merchantId'         => $this->merchantID,
            'partnerReferenceNo' => $partnerReferenceNo,
            'bankCardToken'      => $bankCardToken,
            'chargeToken'        => $chargeToken,
            'otp'                => $otp,
            'amount'             => [
                'value'    => (string) $this->utils->formatAmount($amount['value']),
                'currency' => $amount['currency']
            ],
            'remark'             => $remark,
            'additionalInfo'     => new \stdClass()
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_DEBIT, $token, $data, $timeStamp);
        return Response::autopay($response);
    }

    /**
     * @inheritDoc
     */
    public function debitRefund(
        string $originalPartnerReferenceNo,
        string $partnerRefundNo,
        array $refundAmount = ['value' => 0.00, 'currency' => 'IDR'],
        string $reason = '',
        string $refundType = self::REFUND_TYPE_FULL
    ) {
        if (!in_array($refundType, [self::REFUND_TYPE_FULL, self::REFUND_TYPE_PARTIAL])) {
            throw new \InvalidArgumentException('refundType should be full or partial');
        }

        if (!array_key_exists('value', $refundAmount) || !array_key_exists('currency', $refundAmount)) {
            throw new \InvalidArgumentException('refundAmount should contain key value and currency');
        }

        $token = $this->getToken();
        $timeStamp = $this->utils->getTimeStamp();

        $data = [
            'merchantId'                 => $this->merchantID,
            'originalPartnerReferenceNo' => $originalPartnerReferenceNo,
            'partnerRefundNo'            => $partnerRefundNo,
            'refundAmount'               => [
                'value'    => (string) $this->utils->formatAmount($refundAmount['value']),
                'currency' => $refundAmount['currency']
            ],
            'reason'         => $reason,
            'additionalInfo' => [
                'type' => $refundType
            ]
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_DEBIT_REFUND, $token, $data, $timeStamp);
        return Response::autopay($response);
    }

    /**
     * @inheritDoc
     */
    public function debitStatus(
        string $originalPartnerReferenceNo,
        string $transactionDate, // with format 'Ymd'
        string $serviceCode = self::SERVICECODE_DEBIT,
        array $amount = ['value' => 0.00, 'currency' => 'IDR']
    ) {
        $token = $this->getToken();
        $timeStamp = $this->utils->getTimeStamp();
        $data = [
            'merchantId'                 => $this->merchantID,
            'originalPartnerReferenceNo' => $originalPartnerReferenceNo,
            'transactionDate'            => $transactionDate,
            'serviceCode'                => $serviceCode,
            'amount'                     => [
                'value'    => (string) $this->utils->formatAmount($amount['value']),
                'currency' => $amount['currency']
            ],
            'additionalInfo'             => new \stdClass()
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_DEBIT_STATUS, $token, $data, $timeStamp);
        return Response::autopay($response);
    }

    /**
     * @inheritDoc
     */
    public function limitInquiry(
        string $accountNo,
        string $partnerReferenceNo,
        string $bankCardToken,
        float $amount = 0.00
    ) {
        $token = $this->getToken();
        $timeStamp = $this->utils->getTimeStamp();

        $data = [
            'accountNo'          => $accountNo,
            'partnerReferenceNo' => $partnerReferenceNo,
            'bankCardToken'      => $bankCardToken,
            'additionalInfo'     => [
                'amount' => (string) $this->utils->formatAmount($amount)
            ]
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_LIMIT_INQUIRY, $token, $data, $timeStamp);
        return Response::autopay($response);
    }

    /**
     * @inheritDoc
     */
    public function otp(
        string $partnerReferenceNo,
        string $journeyID,
        string $bankCardToken,
        string $otpReasonCode = self::OTP_CODE_DIRECT_DEBIT,
        array $additionalInfo = ['expiredOtp' => ''],
        string $externalStoreId = ''
    ) {
        // set otpReasonMessage based on otpReasonCode (also minimize the number of func arguments)
        switch ($otpReasonCode) {
            case self::OTP_CODE_CARD_REGISTRATION_SET_LIMIT:
                $otpReasonMessage = 'Card Registration Set Limit';
                break;

            case self::OTP_CODE_ACCOUNT_UNBINDING:
                $otpReasonMessage = 'Account Unbinding';
                break;

            case self::OTP_CODE_FORCE_DEBIT:
                $otpReasonMessage = 'Force Debit';
                break;

            case self::OTP_CODE_DIRECT_DEBIT:
                $otpReasonMessage = 'Direct Debit';
                break;

            default:
                throw new \InvalidArgumentException('otpReasonCode should be 02, 09, 53, or 54');
                break;
        }

        $token = $this->getToken();
        $timeStamp = $this->utils->getTimeStamp();

        $data = [
            'merchantId'         => $this->merchantID,
            'partnerReferenceNo' => $partnerReferenceNo,
            'journeyID'          => $journeyID,
            'bankCardToken'      => $bankCardToken,
            'otpReasonCode'      => $otpReasonCode,
            'otpReasonMessage'   => $otpReasonMessage,
            'additionalInfo'     => $additionalInfo,
            'externalStoreId'    => $externalStoreId
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_OTP, $token, $data, $timeStamp);
        return Response::autopay($response);
    }

    /**
     * @inheritDoc
     */
    public function verifyOtp(
        string $originalPartnerReferenceNo,
        string $originalReferenceNo,
        string $chargeToken,
        string $otp
    ) {
        $token = $this->getToken();
        $timeStamp = $this->utils->getTimeStamp();

        $data = [
            'merchantId'                 => $this->merchantID,
            'originalPartnerReferenceNo' => $originalPartnerReferenceNo,
            'originalReferenceNo'        => $originalReferenceNo,
            'chargeToken'                => $chargeToken,
            'otp'                        => $otp,
            'additionalInfo'             => new \stdClass()
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_OTP_VERIFY, $token, $data, $timeStamp);
        return Response::autopay($response);
    }

    /**
     * @inheritDoc
     */
    public function setLimit(
        string $partnerReferenceNo,
        string $bankCardToken,
        float  $limit          = 0.00,
        string $chargeToken    = '',
        string $otp            = ''
    ) {
        // validate limit
        if ($limit <= 0) {
            throw new \InvalidArgumentException('limit should be greater than 0');
        }
        $limit = $this->utils->formatAmount($limit);

        $token = $this->getToken();
        $timeStamp = $this->utils->getTimeStamp();


        $data = [
            'partnerReferenceNo' => $partnerReferenceNo,
            'bankCardToken'      => $bankCardToken,
            'limit'              => (string) $limit,
            'otp'                => $otp,
            'additionalInfo'     => [
                'chargeToken' => $chargeToken,
                'merchantId'  => $this->merchantID
            ]
        ];

        $response = $this->sendRequest(Constant::URL_AUTOPAY_SET_LIMIT, $token, $data, $timeStamp);
        return Response::autopay($response);
    }
}
