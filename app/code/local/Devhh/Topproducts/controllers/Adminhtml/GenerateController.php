<?php

class Devhh_Topproducts_Adminhtml_GenerateController extends Mage_Adminhtml_Controller_Action
{

    protected function _initAction()
    {
        $this->loadLayout()
            ->_title($this->__('Top Products'))
            ->_setActiveMenu('devhh/topproducts');

        $params = $this->getRequest()->getParams();

        if (isset($params['action'])) {
            /** @var Devhh_Topproducts_Model_General $model */
            $model = Mage::getModel('topproducts/general');
            if ($model->run($params['action'])) {
                Mage::register('action', "{$params['action']} added to Category");
            }
        }
        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()
            ->renderLayout();
    }

}