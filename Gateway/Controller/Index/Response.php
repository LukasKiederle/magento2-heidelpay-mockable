<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 */

namespace TechDivision\PayoneMockable\Gateway\Controller\Index;

use Heidelpay\Gateway\Controller\Index\Response as CoreResponse;
use Heidelpay\Gateway\Helper\Payment as HeidelpayHelper;
use Heidelpay\Gateway\Model\ResourceModel\PaymentInformation\CollectionFactory as PaymentInformationCollectionFactory;
use Heidelpay\Gateway\Model\ResourceModel\PaymentInformation\CollectionFactory;
use Heidelpay\Gateway\Model\TransactionFactory;
use Heidelpay\PhpPaymentApi\Exceptions\HashVerificationException;
use Heidelpay\PhpPaymentApi\Response as HeidelpayResponse;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderCommentSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

/**
 * @copyright  Copyright (c) 2019 TechDivision GmbH
 * @link       http://www.techdivision.com/
 * @author     Lukas Kiederle <l.kiederle@techdivision.com
 */
class Response extends CoreResponse
{
    /** @var QuoteRepository */
    private $quoteRepository;

    /** @var HeidelpayResponse The heidelpay response object */
    private $heidelpayResponse;

    /** @var TransactionFactory */
    private $transactionFactory;

    /** @var CollectionFactory */
    private $paymentInformationCollectionFactory;

    /**
     * heidelpay Response constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\Url\Helper\Data $urlHelper
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteObject
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param HeidelpayHelper $paymentHelper
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     * @param OrderCommentSender $orderCommentSender
     * @param \Magento\Framework\Encryption\Encryptor $encryptor
     * @param \Magento\Customer\Model\Url $customerUrl
     * @param \Magento\Framework\Controller\Result\RawFactory $rawResultFactory
     * @param QuoteRepository $quoteRepository
     * @param PaymentInformationCollectionFactory $paymentInformationCollectionFactory ,
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Quote\Api\CartRepositoryInterface $quoteObject,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        HeidelpayHelper $paymentHelper,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        OrderCommentSender $orderCommentSender,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Customer\Model\Url $customerUrl,
        \Magento\Framework\Controller\Result\RawFactory $rawResultFactory,
        QuoteRepository $quoteRepository,
        PaymentInformationCollectionFactory $paymentInformationCollectionFactory,
        TransactionFactory $transactionFactory
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $checkoutSession,
            $orderFactory,
            $urlHelper,
            $logger,
            $cartManagement,
            $quoteObject,
            $resultPageFactory,
            $paymentHelper,
            $orderSender,
            $invoiceSender,
            $orderCommentSender,
            $encryptor,
            $customerUrl
        );

        $this->resultFactory = $rawResultFactory;
        $this->quoteRepository = $quoteRepository;
        $this->paymentInformationCollectionFactory = $paymentInformationCollectionFactory;
        $this->transactionFactory = $transactionFactory;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function execute()
    {
        // initialize the Raw Response object from the factory.
        $result = $this->resultFactory->create();

        // we just want the response to return a plain url, so we set the header to text/plain.
        $result->setHeader('Content-Type', 'text/plain');

        // the url where the payment will redirect the customer to.
        $redirectUrl = $this->_url->getUrl('hgw/index/redirect', [
            '_forced_secure' => true,
            '_scope_to_url' => true,
            '_nosid' => true
        ]);

        // the payment just wants a url as result, so we set the content to the redirectUrl.
        $result->setContents($redirectUrl);

        // if there is no post request, just do nothing and return the redirectUrl instantly, so an
        // error message can be shown to the customer (which will be created in the redirect controller)
        if (!$this->getRequest()->isPost()) {
            $this->_logger->warning('Heidelpay - Response: Request is not POST.');

            // no further processing.
            return $result;
        }

        // initialize the Response object with data from the request.
        try {
            $this->heidelpayResponse = HeidelpayResponse::fromPost($this->getRequest()->getParams());
        } catch (\Exception $e) {
            $this->_logger->error(
                'Heidelpay - Response: Cannot initialize response object from Post Request. ' . $e->getMessage()
            );

            // return the result now, no further processing.
            return $result;
        }

        $secret = $this->_encryptor->exportKeys();
        $identificationTransactionId = $this->heidelpayResponse->getIdentification()->getTransactionId();

        $this->_logger->debug('Heidelpay secret: ' . $secret);
        $this->_logger->debug('Heidelpay identificationTransactionId: ' . $identificationTransactionId);

        // validate Hash to prevent manipulation
        try {
            $this->heidelpayResponse->verifySecurityHash($secret, $identificationTransactionId);
        } catch (HashVerificationException $e) {
            $this->_logger->critical('Heidelpay Response - HashVerification Exception: ' . $e->getMessage());
            $this->_logger->critical(
                'Heidelpay Response - Received request form server '
                . $this->getRequest()->getServer('REMOTE_ADDR')
                . ' with an invalid hash. This could be some kind of manipulation.'
            );
            $this->_logger->critical(
                'Heidelpay Response - Reference secret hash: '
                . $this->heidelpayResponse->getCriterion()->getSecretHash()
            );

            return $result;
        }

        $this->_logger->debug(
            'Heidelpay - Response: Response object: '
            . print_r($this->heidelpayResponse, true)
        );

        /** @var Order $order */
        $order = null;

        /** @var Quote $quote */
        $quote = null;

        $data = $this->getRequest()->getParams();

        // save the heidelpay transaction data
        list($paymentMethod, $paymentType) = $this->_paymentHelper->splitPaymentCode(
            $this->heidelpayResponse->getPayment()->getCode()
        );

        try {
            // save the response details into the heidelpay Transactions table.
            $transaction = $this->transactionFactory->create();
            $transaction->setPaymentMethod($paymentMethod)
                ->setPaymentType($paymentType)
                ->setTransactionId($this->heidelpayResponse->getIdentification()->getTransactionId())
                ->setUniqueId($this->heidelpayResponse->getIdentification()->getUniqueId())
                ->setShortId($this->heidelpayResponse->getIdentification()->getShortId())
                ->setStatusCode($this->heidelpayResponse->getProcessing()->getStatusCode())
                ->setResult($this->heidelpayResponse->getProcessing()->getResult())
                ->setReturnMessage($this->heidelpayResponse->getProcessing()->getReturn())
                ->setReturnCode($this->heidelpayResponse->getProcessing()->getReturnCode())
                ->setJsonResponse(json_encode($data))
                ->setSource('RESPONSE')
                ->save();
        } catch (\Exception $e) {
            $this->_logger->error('Heidelpay - Response: Save transaction error. ' . $e->getMessage());
        }

        // if something went wrong, return the redirect url without processing the order.
        if ($this->heidelpayResponse->isError()) {
            $message = sprintf(
                'Heidelpay - Response is NOK. Message: [%s], Reason: [%s] (%d), Code: [%s], Status: [%s] (%d)',
                $this->heidelpayResponse->getError()['message'],
                $this->heidelpayResponse->getProcessing()->reason,
                $this->heidelpayResponse->getProcessing()->reason_code,
                $this->heidelpayResponse->getError()['code'],
                $this->heidelpayResponse->getProcessing()->status,
                $this->heidelpayResponse->getProcessing()->getStatusCode()
            );

            $this->_logger->debug($message);

            // return the heidelpay response url as raw response instead of echoing it out.
            $result->setContents($redirectUrl);
            return $result;
        }

        if ($this->heidelpayResponse->isSuccess()) {
            try {
                // get the quote by transactionid from the heidelpay response
                /** @var Quote $quote */
                $quote = $this->quoteRepository->get($this->heidelpayResponse->getIdentification()->getTransactionId());
                $quote->collectTotals();

                /**
                 * Techdivision Changes start here........
                 */

                // in case of guest checkout, set some customer related data.
                if ($this->getRequest()->get('CRITERION_GUEST') === 'true') {
                    $quote->setCustomerId(null)
                        ->setCustomerEmail($quote->getBillingAddress()->getEmail())
                        ->setCustomerIsGuest(true)
                        ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
                }

                /**
                 * Techdivision Changes stop here........
                 */

                // create an order by submitting the quote.
                $order = $this->_cartManagement->submit($quote);
            } catch (\Exception $e) {
                $this->_logger->error('Heidelpay - Response: Cannot submit the Quote. ' . $e->getMessage());

                return $result;
            }

            $data['ORDER_ID'] = $order->getIncrementId();

            $this->_paymentHelper->mapStatus(
                $data,
                $order
            );

            $order->save();
        }

        // if the customer is a guest, we'll delete the additional payment information, which
        // is only used for customer recognition.
        if (isset($quote) && $quote->getCustomerIsGuest()) {
            // create a new instance for the payment information collection.
            $paymentInfoCollection = $this->paymentInformationCollectionFactory->create();

            // load the payment information and delete it.
            /** @var \Heidelpay\Gateway\Model\PaymentInformation $paymentInfo */
            $paymentInfo = $paymentInfoCollection->loadByCustomerInformation(
                $quote->getStoreId(),
                $quote->getBillingAddress()->getEmail(),
                $quote->getPayment()->getMethod()
            );

            if (!$paymentInfo->isEmpty()) {
                $paymentInfo->delete();
            }
        }

        $this->_logger->debug('Heidelpay - Response: redirectUrl is ' . $redirectUrl);

        // return the heidelpay response url as raw response instead of echoing it out.
        $result->setContents($redirectUrl);
        return $result;
    }
}