<?php

/**
 * Catalin Ciobanu
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License (MIT)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @package     Catalin_Seo
 * @copyright   Copyright (c) 2016 Catalin Ciobanu
 * @license     https://opensource.org/licenses/MIT  MIT License (MIT)
 */
class Catalin_SEO_Model_Catalog_Layer_Filter_Attribute extends Mage_Catalog_Model_Layer_Filter_Attribute
{

    protected $_values = array();

    /**
     * Create filter item object
     *
     * @param   string $label
     * @param   mixed $value
     * @param   int $count
     * @return  Mage_Catalog_Model_Layer_Filter_Item
     */
    protected function _createItem($label, $value, $count=0, $optionId = null)
    {
        return Mage::getModel('catalog/layer_filter_item')
            ->setFilter($this)
            ->setLabel($label)
            ->setValue($value)
            ->setOptionId($optionId)
            ->setCount($count);
    }

    /**
     * Initialize filter items
     *
     * @return  Mage_Catalog_Model_Layer_Filter_Abstract
     */
    protected function _initItems()
    {
        $data = $this->_getItemsData();
        $items=array();
        foreach ($data as $itemData) {
            $items[] = $this->_createItem(
                $itemData['label'],
                $itemData['value'],
                $itemData['count'],
                $itemData['option_id']
            );
        }
        $this->_items = $items;
        return $this;
    }
    
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * Apply attribute filter to layer
     *
     * @param Zend_Controller_Request_Abstract $request
     * @param object $filterBlock
     * @return Enterprise_Search_Model_Catalog_Layer_Filter_Attribute
     */
    public function apply(Zend_Controller_Request_Abstract $request, $filterBlock)
    {
        if (!Mage::helper('catalin_seo')->isEnabled()) {
            return parent::apply($request, $filterBlock);
        }

        $filter = $request->getParam($this->_requestVar);
        if (is_array($filter)) {
            return $this;
        }

        if (empty($filter)) {
            return $this;
        }

        $this->_values = explode(Catalin_SEO_Helper_Data::MULTIPLE_FILTERS_DELIMITER, $filter);

        if (!empty($this->_values)) {
            $attrUrlKeyModel = Mage::getResourceModel('catalin_seo/attribute_urlkey');
            $this->_getResource()->applyFilterToCollection($this, $this->_values);
            foreach ($this->_values as $filter) {
                $optionId = $attrUrlKeyModel->getOptionId($this->getAttributeModel()->getId(), $filter);
                $text = $this->_getOptionText($optionId);
                $this->getLayer()->getState()->addFilter($this->_createItem($text, $filter));
                // process all items if multiple choice is enabled
                if (!Mage::helper('catalin_seo')->isMultipleChoiceFiltersEnabled()) {
                    $this->_items = array();
                }
            }
        }

        return $this;
    }

    /**
     * Get data array for building attribute filter items
     *
     * @return array
     */
    protected function _getItemsData()
    {
        if (!Mage::helper('catalin_seo')->isEnabled()) {
            return parent::_getItemsData();
        }

        $attribute = $this->getAttributeModel();

        $key = $this->getLayer()->getStateKey() . '_' . $this->_requestVar;
        $data = $this->getLayer()->getAggregator()->getCacheData($key);

        if ($data === null) {
            $attrUrlKeyModel = Mage::getResourceModel('catalin_seo/attribute_urlkey');
            $options = $attribute->getFrontend()->getSelectOptions();
            $optionsCount = $this->_getResource()->getCount($this);
            $data = array();
            foreach ($options as $option) {
                if (is_array($option['value'])) {
                    continue;
                }
                if (Mage::helper('core/string')->strlen($option['value'])) {
                    // Check filter type
                    if ($this->_getIsFilterableAttribute($attribute) == self::OPTIONS_ONLY_WITH_RESULTS) {
                        if (!empty($optionsCount[$option['value']])) {
                            $data[] = array(
                                'label' => $option['label'],
                                'value' => $attrUrlKeyModel->getUrlValue($attribute->getId(), $option['value']),
                                'count' => $optionsCount[$option['value']],
                                'option_id' => $option['value']
                            );
                        }
                    } else {
                        $data[] = array(
                            'label' => $option['label'],
                            'value' => $attrUrlKeyModel->getUrlValue($attribute->getId(), $option['value']),
                            'count' => isset($optionsCount[$option['value']]) ? $optionsCount[$option['value']] : 0,
                            'option_id' => $option['value']
                        );
                    }
                }
            }

            $tags = array(
                Mage_Eav_Model_Entity_Attribute::CACHE_TAG . ':' . $attribute->getId()
            );

            $tags = $this->getLayer()->getStateTags($tags);
            $this->getLayer()->getAggregator()->saveCacheData($data, $key, $tags);
        }
        
        return $data;
    }

    /**
     * Set request variable name which is used for apply filter
     *
     * @param   string $varName
     * @return  Mage_Catalog_Model_Layer_Filter_Abstract
     */
    public function setRequestVar($varName)
    {
        if (Mage::helper('catalin_seo')->isEnabled()) {
            $attrUrlKeyModel = Mage::getResourceModel('catalin_seo/attribute_urlkey');
            $varName = $attrUrlKeyModel->getUrlKey($varName);
        }

        return parent::setRequestVar($varName);
    }

}
