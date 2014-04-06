<?php

/**
 * Implementation of the EWay RapidAPI
 * 
 * @link http://www.eway.com.au/developers/api/rapid-3-0
 * @author Bartosz Wójcik <bartek@procreative.eu>
 */
class EwayRapid extends CApplicationComponent
{
    const PAYMENT_URL = 'https://au.ewaypayments.com/hotpotato/payment';
    const SOAP_URL = 'https://au.ewaygateway.com/mh/soap.asmx?WSDL';
    

    //Connetion client
    private $_client = null;

    //Client data
    public $CustomerID = null;
    public $username = null;
    public $password = null;
    public $UseLive = null;
    
    public $lastMessage = null;
    public $responseFields = null;
    
    
    /**
     * Return Eway client
     * @return <type>
     */
    public function getClient()
    {
        if ($this->_client === null) {
            if ($this->CustomerID === null || $this->username === null || $this->password === null || $this->UseLive === null)
                throw new Exception ('Eway RapidAPI data fields not set.');

            $this->_client = new SoapClient(self::SOAP_URL, array(
                'trace' => false,
                'exceptions' => true,
            ));
        }

        return $this->_client;
    }
    
    
    public function getAccessCode(User $user, $total, $returnUrl)
    {
        $request = array(
            'Authentication'=>array(
                'CustomerID'=>$this->UseLive ? $this->CustomerID : '87654321',
                'Username'=>$this->UseLive ? $this->username : 'test@eway.com.au',
                'Password'=>$this->UseLive ? $this->password : 'test123',
            ),
            'Customer'=>array(
                'SaveToken'=>false,
                'FirstName'=>$user->profile->firstname,
                'LastName'=>$user->profile->lastname,
                'Street1'=>$user->profile->address_1,
                'City'=>$user->profile->city,
                'State'=>$user->profile->state ? $user->profile->state->code : '',
                'PostalCode'=>$user->profile->postcode,
                'Country'=>strtolower($user->profile->country->code),
                'Email'=>$user->email,
                'Phone'=>$user->profile->phone
            ),
            'Payment'=>array(
                'TotalAmount'=>round($total * 100)
            ),
            'ResponseMode'=>'Redirect',
            'RedirectUrl'=>$returnUrl,
            'IPAddress'=>$_SERVER['REMOTE_ADDR'],
            'BillingCountry'=>strtolower($user->profile->country->code)
        );
        
        $code = '';
        //try {
            $ewayResponseFields = $this->client->CreateAccessCode(array(
                'request'=>$request
            ));
            $code = $ewayResponseFields->CreateAccessCodeResult->AccessCode;
        //}
        //catch (Exception $e) {
            //$this->lastMessage = $e->getMessage();
        //}
        return $code;
    }
    
    public function getAccessCodeResult($code)
    {
        $request = array(
            'Authentication'=>array(
                'CustomerID'=>$this->UseLive ? $this->CustomerID : '87654321',
                'Username'=>$this->UseLive ? $this->username : 'test@eway.com.au',
                'Password'=>$this->UseLive ? $this->password : 'test123',
            ),
            'AccessCode'=>$code
        );
        
        //try {
            $this->responseFields = $this->client->GetAccessCodeResult(array(
                'request'=>$request
            ));
            $result = (int)$this->responseFields->GetAccessCodeResultResult->ResponseCode;
        //}
        //catch (Exception $e) {
            //$this->lastMessage = $e->getMessage();
        //}
        if ($result == 0 || $result == 8 || $result == 10 || $result == 11 || $result == 16) {
            return true;
        }
        else {
            $this->lastMessage = $this->responseFields->GetAccessCodeResultResult->ResponseMessage;
            return false;
        }
    }
	
	public function getTrxnNumber()
    {
        if ($this->responseFields) {
            return $this->responseFields->GetAccessCodeResultResult->TransactionID;
        }
        return '';
    }
}

