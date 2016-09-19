<?php

/**
 * Calculates the shipping cost of an order by using the delivery rate calculator (DRC) of
 * the australian post
 * @see http://www.edeliver.com.au/Templates/ifs/IFS_About_IFS.htm#Rate_Calculation
 * It assumes the "Weight" field in the Buyable class to identify weight
 *
 **/
class AUPostDeliverModifier extends OrderModifier
{

// ######################################## *** model defining static variables (e.g. $db, $has_one)

    public static $db = array(
        "TotalWeight" => "Double",
        "PostalCode" => "Varchar(15)",
        "IsPickUp" => "Varchar(15)",
        "IsValid" => "Boolean",
        "Country" => "Varchar(3)",
        "ServiceType" => "Varchar(20)",
        "DebugString" => "Text"
    );

// ######################################## *** cms variables + functions (e.g. getCMSFields, $searchableFields)

    /**
     * return Object FieldObjetSet
     * standard SS method
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public static $singular_name = "Australian Delivery";
    public function i18n_single_name()
    {
        return _t("AUPostDeliverModifier.AUSTRALIANPOSTDELIVERY", "Australian Post Delivery");
    }

    public static $plural_name = "Australian Deliveries";
    public function i18n_plural_name()
    {
        return _t("AUPostDeliverModifier.AUSTRALIANPOSTDELIVERIES", "Australian Post Deliveries");
    }


// ######################################## *** other (non) static variables (e.g. protected static $special_name_for_something, protected $order)

    protected static $shippingAddress, $billingAddress;

    /**
     *@var String
     * URL for connecting with AU Post
     */
    protected static $url = 'http://drc.edeliver.com.au/ratecalc.asp?';
    public static function set_url($s)
    {
        self::$url = $s;
    }
    public static function get_url()
    {
        return self::$url;
    }

    /**
     *@var Boolean
     * can the customer pick up from the store?
     */
    protected static $allow_pick_up = false;
    public static function set_allow_pick_up($b)
    {
        self::$allow_pick_up = $b;
    }
    public static function get_allow_pick_up()
    {
        return self::$allow_pick_up;
    }

    /**
     *@var String
     * postal code from which the product is picked up (the online store warehouse)
     */
    protected static $pickup_post_code = 2000;
    public static function set_pickup_post_code($s)
    {
        self::$pickup_post_code = $s;
    }
    public static function get_pickup_post_code()
    {
        return self::$pickup_post_code;
    }

    /**
     *@var Integer
     * assummed width and height and length in millimeters
     * In a perfect world we would calculate this as well.
     */
    protected static $assumed_dimension = 160;
    public static function set_assumed_dimension($i)
    {
        self::$assumed_dimension = $i;
    }
    public static function get_assumed_dimension()
    {
        return self::$assumed_dimension;
    }

    /**
     *@var Integer
     * assummed width and height and length in millimeters
     * In a perfect world we would calculate this as well.
     */
    protected static $minimum_weight = 0;
    public static function set_minimum_weight($i)
    {
        self::$minimum_weight = $i;
    }
    public static function get_minimum_weight()
    {
        return self::$minimum_weight;
    }

    /**
     *@var Float
     * assummed width and height and length in millimeters
     * In a perfect world we would calculate this as well.
     */
    protected static $handling_charge = 2;
    public static function set_handling_charge($f)
    {
        self::$handling_charge = $i;
    }
    public static function get_handling_charge()
    {
        return self::$handling_charge;
    }

    /**
     *@var Float
     * the value charge if the postal code can not be found
     */
    protected static $error_value = 9.99;
    public static function set_error_value($f)
    {
        self::$error_value = $i;
    }
    public static function get_error_value()
    {
        return self::$error_value;
    }


    /**
     *@var Array
     * delivery type options (e.g. fast post, express post, etc...)
     */
    public static $service_types = array(
        "domestic" => array('STANDARD', 'EXPRESS', 'EXP_PLT'),
        "international" => array('AIR', 'SEA', 'ECI_D', 'ECI_M', 'EPI')
    );

    /**
     *@var string
     * the service type that applies to this online store
     * there are two types: international + domestic
     */
    protected static $service_type;
    public static function get_service_type()
    {
        return self::$service_type;
    }
    public static function set_service_type($type, $isDomestic)
    {
        $index = $isDomestic ? "domestic" : "international";
        $types = self::$service_types[$index];
        if (in_array($type, $types)) {
            self::$service_type[$index] = $type;
        }
    }



// ######################################## *** CRUD functions (e.g. canEdit)
// ######################################## *** init and update functions
    /**
     * updates database fields
     * @param Bool $force - run it, even if it has run already
     * @return void
     */
    public function runUpdate($force = true)
    {
        if (isset($_GET['debug_profile'])) {
            Profiler::mark('AUPostDeliverModifier::runUpdate');
        }
        //ORDER IS CRUCIAL HERE...
        $this->checkField("PostalCode");
        $this->checkField("IsPickUp");
        $this->checkField("TotalWeight");
        $this->checkField("Country");
        $this->checkField("ServiceType");
        $this->LiveCalculatedTotal();
        $this->checkField("TableSubTitle");
        //this must be last!
        if (isset($_GET['debug_profile'])) {
            Profiler::unmark('AUPostDeliverModifier::runUpdate');
        }
        parent::runUpdate($force);
    }

    public function updateIsPickUp($boolean)
    {
        $this->IsPickUp = ($boolean ? 1 : 0);
        $this->write();
    }

    public function updateCountry($code)
    {
        $order = $this->Order();
        if ($order->UseShippingAddress) {
            $shippingAddress = $order->CreateOrReturnExistingAddress("ShippingAddress");
            $shippingAddress->ShippingCountry = $code;
            $shippingAddress->write();
        } else {
            $billingAddress = $order->CreateOrReturnExistingAddress("BillingAddress");
            $billingAddress->Country = $code;
            $billingAddress->write();
        }
        $this->Country = $code;
        $this->write();
    }

    public function updatePostalCode($code)
    {
        $order = $this->Order();
        if ($order->UseShippingAddress) {
            $shippingAddress = $order->CreateOrReturnExistingAddress("ShippingAddress");
            $shippingAddress->ShippingPostalCode = $code;
            $shippingAddress->write();
        } else {
            $billingAddress = $order->CreateOrReturnExistingAddress("BillingAddress");
            $billingAddress->PostalCode = $code;
            $billingAddress->write();
        }
        $this->PostalCode = $code;
        $this->write();
    }


// ######################################## *** form functions (e. g. showform and getform)

    /**
     *@var String
     * standard Order Modifier Variable
     */


    public function showForm()
    {
        if ($this->Order()->Items()) {
            Requirements::javascript("ecommerce_au_connectivity/javascript/AUPostDeliverModifier.js");
            return true;
        }
        return false;
    }

    /**
     * standard Order Modifier method
     * @return Object Form
     * returns the order modifier form extension for this modifier.
     * @todo: add option to choose delivery option (fast post, slow post, etc...)
     */
    public function getModifierForm($optionalController = null, $optionalValidator = null)
    {
        $fields = new FieldSet();
        $fields->push($this->headingField());
        $fields->push($this->descriptionField());
        if (self::get_allow_pick_up()) {
            $fields->push(new CheckboxField('IsPickUp', _t("AUPostDeliverModifier.ISPICKUP", "I would like to pick up this order?"), $this->IsPickUp));
        }
        $fields->push(new TextField('PostalCodeForDelivery', _t("AUPostDeliverModifier.POSTALCODEFORDELIVERY", "Postal code for delivery"), $this->PostalCode, 4));
        $actions = new FieldSet(
            new FormAction('submit', _t("AUPostDeliverModifier.SUBMITFORM", "Update Order"))
        );
        return new AUPostDeliverModifier_Form($optionalController, 'AUPostDeliverModifier', $fields, $actions, $optionalValidator);
    }



// ######################################## *** template functions (e.g. ShowInTable, TableTitle, etc...) ...  ... USES DB VALUES

    /**
     * @return Boolean
     * Standard OrderModifier method
     */
    public function ShowInTable()
    {
        return true;
    }




// ######################################## ***  inner calculations....  ... USES CALCULATED VALUES




// ######################################## *** calculate database fields: protected function Live[field name]  ... USES CALCULATED VALUES

    /**
     * standard Order Modifier Method
     * @return String
     * @TODO return the type of delivery! Maybe we can get the customer to choose the type of delivery?
     */
    protected function LiveName()
    {
        $string = "";
        if ($this->IsValid) {
            if ($this->IsPickUp) {
                $string = _t("AUPostDeliverModifier.PICKUPFROMSTORE", 'Pick-up from store');
            } else {
                $string = _t("AUPostDeliverModifier.AUSTRALIANPOST", 'Australian Post Delivery');
            }
        } else {
            $string .= _t("AUPostDeliverModifier.DELIVERYCOSTCANNOTBEDETERMINED", 'Delivery costs can not be calculated');
            if ($this->DebugString) {
                $string .= _t("AUPostDeliverModifier.COLON", ': ').$this->DebugString;
            }
        }
        $string .= _t("AUPostDeliverModifier.DETAILS", ' --- Details')._t("AUPostDeliverModifier.COLON", ': ');
        $string .= _t("AUPostDeliverModifier.WEIGHT", 'weight')._t("AUPostDeliverModifier.COLON", ': ');
        if ($this->TotalWeight) {
            $string .= round($this->TotalWeight/1000, 1)._t("AUPostDeliverModifier.KG", 'kg.');
        } else {
            $string .= _t("AUPostDeliverModifier.NOT_SET", 'Not Set');
        }
        $string .= _t("AUPostDeliverModifier.COMMA", ', ')._t("AUPostDeliverModifier.POSTAL_CODE", 'postal code')._t("AUPostDeliverModifier.COLON", ': ');
        if ($this->PostalCode) {
            $string .= $this->PostalCode;
        } else {
            $string .= _t("AUPostDeliverModifier.NOT_SET", 'Not Set');
            ;
        }
        if ($this->Country && $this->Country != "AU") {
            $string .= " (". $this->Country.") ";
        } elseif (!$this->Country) {
            $string .= _t("AUPostDeliverModifier.COMMA", ', ')._t("AUPostDeliverModifier.COUNTRY", 'Country').' '._t("AUPostDeliverModifier.NOT_SET", 'Not Set');
            ;
        }
        return $string;
    }


    /**
     * @return String
     * Standard OrderModifier method
     */
    public function LiveTableSubTitle()
    {
        $postalCode = $this->PostalCode;
        if ($postalCode) {
            $postalCode = " (".$postalCode.")";
        }
        return _t("AUPostDeliverModifier.PLEASECOMPLETEPOSTALCODE", 'Please provide a valid postal code to get the best estimate.');
    }

    /**
     * standard Order Modifier Method
     * @return Boolean
     */
    protected function LiveIsPickup()
    {
        return $this->IsPickUp;
    }

    /**
     * Used to save Country to database
     *
     * determines value for DB field: Country
     * @return String
     */
    protected function LiveCountry()
    {
        return EcommerceCountry::get_country();
    }

    /**
     * @return Double
     * returns the total weight for the order.
     * We need to work out if the orderitem actually knows the weight!
     */
    protected function LiveTotalWeight()
    {
        $totalWeight = self::get_minimum_weight();
        $order = $this->Order();
        $orderItems = $order->Items();
        // Calculate the total weight of the order
        if ($orderItems) {
            foreach ($orderItems as $orderItem) {
                $buyable = $orderItem->Buyable();
                if ($buyable) {
                    if (method_exists($buyable, "getWeight")) {
                        $weight = $buyable->getWeight();
                    } else {
                        $weight = $buyable->Weight;
                    }
                    $weight = floatval($weight) - 0;
                    $totalWeight += $weight * $orderItem->Quantity;
                }
            }
        }
        return $totalWeight;
    }

    /**
     * @return String
     * returns the postal code for the delivery - if known...
     */
    protected function LivePostalCode()
    {
        $order = $this->Order();
        if ($order->UseShippingAddress) {
            if ($shippingAddress = $order->ShippingAddress()) {
                return $shippingAddress->ShippingPostalCode;
            }
        } else {
            if ($billingAddress = $order->BillingAddress()) {
                return $billingAddress->PostalCode;
            }
        }
    }

    /**
     * @return String
     * returns the delivery type for the order...
     */
    protected function LiveServiceType()
    {
        $inputs['Country'] = $this->Country;
        if ("AU" == $this->Country) {
            $index = "domestic";
        } else {
            $index = "international";
        }
        $type = self::$service_types[$index][0];
        if (self::$service_type && isset(self::$service_type[$index])) {
            $type = self::$service_type[$index];
        }
        return $type;
    }

    /**
     * @var static Double
     * reduce calculation time by
     */
    private static $calculated_total_in_memory = null;

    /**
     * Calculates the extra charges from the order based on the weight attribute of a product
     * ASSUMPTION -> weight in grams
     * @return Double
     */
    public function LiveCalculatedTotal($code = null)
    {
        $oldIsValidValue = $this->IsValid;
        if ($this->IsPickUp || $this->TotalWeight == 0) {
            self::$calculated_total_in_memory = 0;
            $this->IsValid = true;
        } else {
            $this->IsValid = false;
            $inputs = array();
            $postalCode = ($code ? $code : $this->PostalCode);
            $inputs['Pickup_Postcode'] = self::get_pickup_post_code();
            $inputs['Destination_Postcode'] = $postalCode;
            $inputs['Country'] = $this->Country;
            $inputs['Service_Type'] = $this->ServiceType;
            $inputs['Weight'] = $this->TotalWeight;
            $inputs['Quantity'] = 1;
            $inputs['Length'] = $inputs['Width'] = $inputs['Height'] = self::get_assumed_dimension();
            if (!$inputs['Destination_Postcode']) {
                $this->DebugString = _t("AUPostDeliverModifier.NOPOSTALCODE", "Please provide valid postal code");
            } elseif (
                $AUPostDeliverModifier_LocalMemory = AUPostDeliverModifier_LocalMemory::get_record(
                    $inputs['Quantity'],
                    $inputs['Weight'],
                    $inputs['Length'],
                    $inputs['Width'],
                    $inputs['Height'],
                    $inputs['Pickup_Postcode'],
                    $inputs['Destination_Postcode'],
                    $inputs['Country'],
                    $inputs['Service_Type']
                )
            ) {
                self::$calculated_total_in_memory = floatval($AUPostDeliverModifier_LocalMemory->Cost);
                $this->IsValid = true;
            } else {
                foreach ($inputs as $name => $value) {
                    $params[] = "$name=$value";
                }
                $url = self::$url . implode('&', $params);
                //var_dump($url);
                $result = @file($url);
                if ($result) {
                    foreach ($result as $field) {
                        list($name, $value) = explode('=', $field);
                        $fields[$name] = $value;
                    }
                    if (strstr($fields['err_msg'], 'OK') !== false && isset($fields['charge'])) {
                        self::$calculated_total_in_memory = floatval($fields['charge']);
                        $this->IsValid = true;
                        $AUPostDeliverModifier_LocalMemory = AUPostDeliverModifier_LocalMemory::set_record(
                            $inputs['Quantity'],
                            $inputs['Weight'],
                            $inputs['Length'],
                            $inputs['Width'],
                            $inputs['Height'],
                            $inputs['Pickup_Postcode'],
                            $inputs['Destination_Postcode'],
                            $inputs['Country'],
                            $inputs['Service_Type'],
                            $fields['charge']
                        );
                        $this->DebugString = "";
                    } elseif (isset($fields['err_msg'])) {
                        $this->DebugString = $fields['err_msg'];
                    }
                } else {
                    $this->DebugString = "Could not retrieve data from $url";
                }
            }
            self::$calculated_total_in_memory += self::get_handling_charge();
        }
        if ($oldIsValidValue != $this->IsValid) {
            $this->write();
        }
        return self::$calculated_total_in_memory;
    }

    public function LiveTableValue()
    {
        return $this->LiveCalculatedTotal();
    }

// ######################################## *** Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)
// ######################################## *** standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->IsPickUp) {
            $this->CalculatedTotal = 0;
            $this->TableValue = 0;
        }
        if (!$this->IsValid) {
            $this->CalculatedTotal = self::get_error_value();
            $this->TableValue = self::get_error_value();
        }
    }
// ######################################## *** AJAX related functions
// ######################################## *** debug functions


// ######################################## *** AJAX related functions
    /**
    * some modifiers can be hidden after an ajax update (e.g. if someone enters a discount coupon and it does not exist).
    * There might be instances where ShowInTable (the starting point) is TRUE and HideInAjaxUpdate return false.
    *@return Boolean
    **/
    public function HideInAjaxUpdate()
    {
        //we check if the parent wants to hide it...
        //we need to do this first in case it is being removed.
        if (parent::HideInAjaxUpdate()) {
            return true;
        }
        // we do NOT hide it if values have been entered
        if ($this->PostalCode) {
            return false;
        }
        return true;
    }
}

class AUPostDeliverModifier_Form extends OrderModifierForm
{
    public function __construct($optionalController = null, $name, FieldSet $fields, FieldSet $actions, $validator = null)
    {
        parent::__construct($optionalController, $name, $fields, $actions, $validator);
    }

    public function submit($data, $form)
    {
        $order = ShoppingCart::current_order();
        $modifiers = $order->Modifiers();
        foreach ($modifiers as $modifier) {
            if (get_class($modifier) == 'AUPostDeliverModifier') {
                if (isset($data['IsPickUp'])) {
                    $isPickUp = true;
                } else {
                    $isPickUp = false;
                }
                $modifier->updateIsPickUp($isPickUp);
                return ShoppingCart::singleton()->setMessageAndReturn(_t("AUPostDeliverModifier.UPDATEDDELIVERY", "Updated delivery.", "good"));
            }
        }
        return ShoppingCart::singleton()->setMessageAndReturn(_t("AUPostDeliverModifier.NOTUPDATEDDELIVERY", "Delivery was not updated.", "bad"));
    }
}

class AUPostDeliverModifier_Controller extends Controller
{
    public static $url_segment = 'aupostdelivermodifier';

    /**
     * set new postal code and country....
     */
    public function amount()
    {
        $postalCode = null;
        $country = null;
        if (isset($_REQUEST['postcode'])) {
            $postalCode = $_REQUEST['postcode'];
        }
        if (isset($_REQUEST['country'])) {
            $country = $_REQUEST['country'];
        }
        $order = ShoppingCart::current_order();
        $modifiers = $order->Modifiers();
        foreach ($modifiers as $modifier) {
            if (is_a($modifier, 'AUPostDeliverModifier')) {
                if ($postalCode) {
                    $modifier->updatePostalCode(Convert::raw2sql($postalCode));
                }
                if ($country) {
                    $modifier->updateCountry(Convert::raw2sql($country));
                }
                return ShoppingCart::singleton()->setMessageAndReturn(_t("AUPostDeliverModifier.UPDATEDPOSTALCODE", "Updated postal code.", "good"));
            }
        }
        return ShoppingCart::singleton()->setMessageAndReturn(_t("AUPostDeliverModifier.NOTUPDATED", "Postal code was not updated.", "bad"));
    }
}


class AUPostDeliverModifier_LocalMemory extends DataObject
{
    public static $db = array(
        "Quantity" => "Int",
        "Weight" => "Int",
        "Length" => "Int",
        "Width" => "Int",
        "Height" => "Int",
        "FromPostalCode" => "Int",
        "ToPostalCode" => "Int",
        "Country" => "Varchar(3)",
        "Type" => "Varchar(30)",
        "Cost" => "Currency"
    );

    public static $indexes = array(
        "Quantity" => true,
        "Weight" => true,
        "Length" => true,
        "Width" => true,
        "Height" => true,
        "FromPostalCode" => true,
        "ToPostalCode" => true,
        "Country" =>  true,
        "Type" => true
    );

    public static function set_record(
        $quantity,
        $weight,
        $length,
        $width,
        $height,
        $fromPostalCode,
        $toPostalCode,
        $country,
        $type,
        $cost
    ) {
        $obj = new AUPostDeliverModifier_LocalMemory();
        $obj->Quantity = $quantity;
        $obj->Weight = $weight;
        $obj->Length = $length;
        $obj->Width = $width;
        $obj->Height = $height;
        $obj->FromPostalCode = $fromPostalCode;
        $obj->ToPostalCode = $toPostalCode;
        $obj->Country = $country;
        $obj->Type = $type;
        $obj->Cost = $cost;
        $obj->write();
    }

    public static function get_record(
        $quantity,
        $weight,
        $length,
        $width,
        $height,
        $fromPostalCode,
        $toPostalCode,
        $country,
        $type
    ) {
        $oneWeekAgo = strtotime("-1 week");
        $where = "
				\"Quantity\" = ".intval($quantity)." AND
				\"Weight\" = ".intval($weight)." AND
				\"Length\" = ".intval($length)." AND
				\"Width\" = ".intval($width)." AND
				\"Height\" = ".intval($height)." AND
				\"FromPostalCode\" = ".intval($fromPostalCode)." AND
				\"ToPostalCode\" = ".intval($toPostalCode)." AND
				\"Country\" = '$country' AND
				\"Type\" = '$type' AND
				UNIX_TIMESTAMP(\"Created\") > $oneWeekAgo
		";
        $obj = DataObject::get_one(
            "AUPostDeliverModifier_LocalMemory",
            $where
        );
        return $obj;
    }
}
