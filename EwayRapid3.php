<?php
require_once('Rapid3.0.php');

/**
 * Implementation of the EWay Rapid 3.0 API
 * 
 * @link http://www.eway.com.au/developers/api/rapid-3-0
 * @author Bartosz Wójcik <bartek@procreative.eu>
 */
class EwayRapid3 extends CApplicationComponent
{
    //Connection client
    private $_client = null;

    //Client data
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
            if ($this->UseLive === null)
                throw new Exception ('Eway Rapid 3.0 API data fields not set.');

            $this->_client = new RapidAPI($this->UseLive);
        }

        return $this->_client;
    }
    
    
    public function getAccessCode(User $user, DeliveryForm $delivery, $currencyCode, $total, $returnUrl)
    {
        $request = new CreateAccessCodeRequest();
        
        $request->Customer->FirstName = $user->profile->firstname;
        $request->Customer->LastName = $user->profile->lastname;
        $request->Customer->Street1 = $user->profile->address_1;
        $request->Customer->Street2 = $user->profile->address_2;
        $request->Customer->City = $user->profile->city;
        $request->Customer->State = $user->profile->state ? $user->profile->state->code : '';
        $request->Customer->PostalCode = $user->profile->postcode;
        $request->Customer->Country = strtolower($user->profile->country->code);
        $request->Customer->Email = $user->email;
        $request->Customer->Phone = str_replace('-', ' ', $user->profile->phone);
        
        if ($delivery && $delivery->useIt) {
            $deliveryName = explode(' ', $delivery->delivery_name, 2);
            
            $request->ShippingAddress->FirstName = $deliveryName[0];
            $request->ShippingAddress->LastName = count($deliveryName) > 1 ? $deliveryName[1] : '';
            $request->ShippingAddress->Street1 = $delivery->delivery_address_1;
            $request->ShippingAddress->Street2 = $delivery->delivery_address_2;
            $request->ShippingAddress->City = $delivery->delivery_city;
            $request->ShippingAddress->State = $delivery->delivery_state_id ? $delivery->state->code : '';
            $request->ShippingAddress->Country = strtolower($delivery->country->code);
            $request->ShippingAddress->PostalCode = $delivery->delivery_postcode;
        }
        else {
            $request->ShippingAddress->FirstName = $user->profile->firstname;
            $request->ShippingAddress->LastName = $user->profile->lastname;
            $request->ShippingAddress->Street1 = $user->profile->address_1;
            $request->ShippingAddress->Street2 = $user->profile->address_2;
            $request->ShippingAddress->City = $user->profile->city;
            $request->ShippingAddress->State = $user->profile->state ? $user->profile->state->code : '';
            $request->ShippingAddress->Country = strtolower($user->profile->country->code);
            $request->ShippingAddress->PostalCode = $user->profile->postcode;
            $request->ShippingAddress->Email = $user->email;
            $request->ShippingAddress->Phone = str_replace('-', ' ', $user->profile->phone);
        }
        
        //Populate values for Payment Object
        //Note: TotalAmount is a Required Field When Process a Payment, TotalAmount should set to "0" or leave EMPTY when Create/Update A TokenCustomer
        $request->Payment->TotalAmount = round($total * 100);
        if ($currencyCode) {
            $request->Payment->CurrencyCode = $currencyCode;
        }

        //Url to the page for getting the result with an AccessCode
        //Note: RedirectUrl is a Required Field For all cases
        $request->RedirectUrl = $returnUrl;        
        //Customer IP - we process the payment using Beagle
        //$request->CustomerIP = $_SERVER['REMOTE_ADDR'];
        //Method for this request. e.g. ProcessPayment, Create TokenCustomer, Update TokenCustomer & TokenPayment
        $request->Method = 'ProcessPayment';

        //Call RapidAPI
        $this->responseFields = $this->client->CreateAccessCode($request);

        //Check if any error returns
        if(isset($this->responseFields->Errors))
        {
            //Get Error Messages from Error Code. Error Code Mappings are in the Config.ini file
            $ErrorArray = explode(",", $this->responseFields->Errors);

            $lblError = "";

            foreach ( $ErrorArray as $error )
            {
                if(isset($this->client->APIConfig[$error]))
                    $lblError .= $error." ".$this->client->APIConfig[$error]."<br/>";
                else
                    $lblError .= $error;
            }
            $this->lastMessage = $lblError;
            
            return null;
        }
        
        return $this->responseFields->AccessCode;
    }
    
    public function getAccessCodeResult($code)
    {
        $request = new GetAccessCodeResultRequest();

        $request->AccessCode = $code;

        //Call RapidAPI to get the result
        $this->responseFields = $this->client->GetAccessCodeResult($request);

        //Check if any error returns
        if(isset($this->responseFields->Errors))
        {
            //Get Error Messages from Error Code. Error Code Mappings are in the Config.ini file
            $ErrorArray = explode(",", $this->responseFields->Errors);

            $lblError = "";
            foreach ( $ErrorArray as $error )
            {
                $lblError .= $this->client->APIConfig[$error]."<br>";
            }
            $this->lastMessage = $lblError;
            
            return false;
        }
        
        if (isset($this->responseFields->ResponseMessage)) {
            //Get Error Messages from Error Code. Error Code Mappings are in the Config.ini file
            $ResponseMessageArray = explode(",", $this->responseFields->ResponseMessage);

            $responseMessage = "";
            foreach ( $ResponseMessageArray as $message )
            {
                if (isset($this->client->APIConfig[$message]))
                    $responseMessage .= $message . " " . $this->client->APIConfig[$message]."<br/>";
                else
                    $responseMessage .= $message;
            }
            $this->lastMessage = $responseMessage;
        }
        
        $result = (int)$this->responseFields->ResponseCode;        
        if ($result == 0 || $result == 8 || $result == 10 || $result == 11 || $result == 16) {
            return true;
        }
        
        return false;
    }
    
    public function getFormActionURL()
    {
        if ($this->responseFields) {
            return $this->responseFields->FormActionURL;
        }
        return '';
    }
	
	public function getTrxnNumber()
    {
        if ($this->responseFields) {
            return $this->responseFields->TransactionID;
        }
        return '';
    }
}
