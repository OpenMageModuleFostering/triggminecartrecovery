<?php
class Videal_Triggmine_Adminhtml_TriggminebackendController extends Mage_Adminhtml_Controller_Action
{
	public function indexAction()
    {
       $this->loadLayout();
	   $this->_title($this->__("Triggmine"));
	   $this->renderLayout();
    }
}