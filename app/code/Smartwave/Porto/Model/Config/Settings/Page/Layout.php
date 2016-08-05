<?php
namespace Smartwave\Porto\Model\Config\Settings\Page;

class Layout implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [['value' => 'one_column', 'label' => __('1 Column')], ['value' => 'two_column_left', 'label' => __('2 Columns with Left Sidebar')], ['value' => 'two_column_right', 'label' => __('2 Columns with Right Sidebar')], ['value' => 'three_column', 'label' => __('3 Columns')]];
    }

    public function toArray()
    {
        return ['one_column' => __('1 Column'), 'two_column_left' => __('2 Columns with Left Sidebar'), 'two_column_right' => __('2 Columns with Right Sidebar'), 'three_column' => __('3 Columns')];
    }
}
