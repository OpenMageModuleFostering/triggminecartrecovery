<?php

class Triggmine_IntegrationModule_Model_Observer
{
    public function send_page_init(Varien_Event_Observer $observer)
    {   
        if (Mage::helper('integrationmodule/data')->isEnabled())
        {
            $data = Mage::helper('integrationmodule/data')->PageInit($observer);
            Mage::helper('integrationmodule/data')->onPageInit($data);
        }
    }    
    
    public function diagnostic_information_updated(Varien_Event_Observer $observer)
    {   
        $data = Mage::helper('integrationmodule/data')->SoftChek($observer);
        Mage::helper('integrationmodule/data')->onDiagnosticInformationUpdated($data);
    }
    
    public function export_order_history(Varien_Event_Observer $observer)
    {
        if (Mage::helper('integrationmodule/data')->isEnabled() &&
            Mage::helper('integrationmodule/data')->exportEnabled())
        {
            $data = Mage::helper('integrationmodule/data')->getOrderHistory($observer);
            Mage::helper('integrationmodule/data')->exportOrderHistory($data);
        }
    }
    
    public function SalesOrderPlaceAfter(Varien_Event_Observer $observer)
    {
        if (Mage::helper('integrationmodule/data')->isEnabled())
        {
            $data = Mage::helper('integrationmodule/data')->getOrderData($observer);
            Mage::helper('integrationmodule/data')->onConvertCartToOrder($data);
        }
    }

    public function CheckoutCartSaveAfter(Varien_Event_Observer $observer)
    {
        if (Mage::helper('integrationmodule/data')->isEnabled())
        {
            $data = Mage::helper('integrationmodule/data')->getCartData();
            Mage::helper('integrationmodule/data')->sendCart($data);
        }
    }

    public function CustomerRegisterSuccess(Varien_Event_Observer $observer)
    {
        if (Mage::helper('integrationmodule/data')->isEnabled())
        {
            $event = $observer->getEvent();
            
            $data = Mage::helper('integrationmodule/data')->getCustomerRegisterData($event);
            Mage::helper('integrationmodule/data')->sendRegisterData($data);
        }
    }

    public function CustomerLogin(Varien_Event_Observer $observer)
    {
        if (Mage::helper('integrationmodule/data')->isEnabled())
        {
            $data = Mage::helper('integrationmodule/data')->getCustomerLoginData();
            Mage::helper('integrationmodule/data')->sendLoginData($data);
        }
    }

    public function CustomerLogout(Varien_Event_Observer $observer)
    {
        if (Mage::helper('integrationmodule/data')->isEnabled())
        {
            $data = Mage::helper('integrationmodule/data')->getCustomerLoginData();
            Mage::helper('integrationmodule/data')->sendLogoutData($data);
        }
    }
}