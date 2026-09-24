<?php
namespace WeltPixel\AdvancedWishlist\Controller\Multiwishlist;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Wishlist\Model\WishlistFactory;
use WeltPixel\AdvancedWishlist\Model\MultipleWishlistProvider;

class Update extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{

    /**
     * @var WishlistFactory
     */
    protected $wishlistFactory;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var MultipleWishlistProvider
     */
    protected $multipleWishlistProvider;

    /**
     * @var FormKeyValidator
     */
    protected $formKeyValidator;

    /**
     * Update constructor.
     * @param WishlistFactory $wishlistFactory
     * @param CustomerSession $customerSession
     * @param MultipleWishlistProvider $multipleWishlistProvider
     * @param FormKeyValidator $formKeyValidator
     * @param Context $context
     */
    public function __construct(
        WishlistFactory $wishlistFactory,
        CustomerSession $customerSession,
        MultipleWishlistProvider $multipleWishlistProvider,
        FormKeyValidator $formKeyValidator,
        Context $context
    ) {
        parent::__construct($context);
        $this->wishlistFactory = $wishlistFactory;
        $this->customerSession = $customerSession;
        $this->multipleWishlistProvider = $multipleWishlistProvider;
        $this->formKeyValidator = $formKeyValidator;
    }

    public function execute()
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_redirect('/');
            return;
        }

        $result = [
            'result' => false,
            'reload' => true
        ];
        $customerId = $this->customerSession->getCustomerId();

        if (!$customerId || !$this->formKeyValidator->validate($this->getRequest())) {
            return $this->prepareResult($result);
        }

        $wishlistId = $this->getRequest()->getParam('wishlist-id', null);
        $wishlistName = $this->getRequest()->getParam('wishlist-name', '');
        /**
         * This action both creates a wishlist and edits an existing one. Editing now loads the row
         * filtered by its owner, so an id belonging to another customer does not match and nothing
         * is written. Only a newly created wishlist has the customer id set on it: setting it on an
         * existing row is what allowed another customer's wishlist to be reassigned to the caller,
         * together with its name and its sharing code.
         */
        if ($wishlistId) {
            $wishlistModel = $this->multipleWishlistProvider->getCustomerWishlist($wishlistId, $customerId);

            if (!$wishlistModel) {
                return $this->prepareResult($result);
            }
        } else {
            $wishlistModel = $this->wishlistFactory->create();
            $wishlistModel->setCustomerId($customerId);
        }

        try {
            $wishlistModel->setWishlistName($wishlistName);
            if (!$wishlistModel->getSharingCode()) {
                $wishlistModel->generateSharingCode();
            }
            $wishlistModel->save();
            $result['result'] = true;
            if ($wishlistId) {
                $result['reload'] = false;
            }

        } catch (\Exception $e) {
            $result['reload'] = false;
            $result['msg'] = $e->getMessage();
            return $this->prepareResult($result);
        }

        return $this->prepareResult($result);
    }

    /**
     * @param array $result
     * @return string
     */
    protected function prepareResult($result)
    {
        $jsonData = json_encode($result);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($jsonData);
    }
}
