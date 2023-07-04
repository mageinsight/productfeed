<?php
namespace MageInsight\ProductFeed\Plugin\Block\Adminhtml\Product\Attribute\Edit\Tab;

class Front
{
    /**
     * @var \Magento\Config\Model\Config\Source\Yesno
     */
    protected $custom;

    /**
     * \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * Constructor
     * 
     * @param \Magento\Config\Model\Config\Source\Yesno $custom
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Config\Model\Config\Source\Yesno $custom,
        \Magento\Framework\Registry $registry
    ) {
        $this->custom = $custom;
        $this->registry = $registry;
    }

    /**
     * Add additional field in the admin attribute edit form
     * 
     * @param \Magento\Catalog\Block\Adminhtml\Product\Attribute\Edit\Tab\Front $subject
     * @param \Closure $proceed
     * 
     * @return object
     */
    public function aroundGetFormHtml(
        \Magento\Catalog\Block\Adminhtml\Product\Attribute\Edit\Tab\Front $subject,
        \Closure $proceed
    )
    {
        $customSource = $this->custom->toOptionArray();
        $attributeObject = $this->getAttributeObject();
        $form = $subject->getForm();
        $fieldset = $form->getElement('front_fieldset');
        $fieldset->addField(
            'used_in_feed',
            'select',
            [
                'name' => 'used_in_feed',
                'label' => __('Used in Feed'),
                'title' => __('Used in Feed'),
                'value' => $attributeObject->getData('used_in_feed'),
                'values' => $customSource,
            ]
        );
        return $proceed();
    }

    /**
     * Get current attribute's object
     */
    private function getAttributeObject()
    {
        return $this->registry->registry('entity_attribute');
    }
}