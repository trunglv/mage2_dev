# mage2_dev - A few CLI tools for Magento 2 Development and Deployment 

## Installation

##### 1. Clone and copy a source code into a folder app/code/Betagento 


##### 2. Install from Composer
```
composer require trunglv/mage2-dev:dev-main [Recommend]
```

## Debug tools
### 1. List all plugins for a class
Example:
```
bin/magento beta_dev:show_plugins -t "Magento\InventorySales\Model\AreProductsSalableForRequestedQty"
```

Examples of results:

<pre>bin/magento beta_dev:show_plugins -t &apos;Magento\Quote\Api\CartManagementInterface&apos;
<font color="#EF2929"><u style="text-decoration-style:single"><b> ------Plugins for Scope global------ </b></u></font>

<font color="#8AE234"><u style="text-decoration-style:single"><b>Plugins for type Magento\Quote\Api\CartManagementInterface</b></u></font>

+--------------------+-----------------+--------------------+--------------------------------------------------+---------------+
|<font color="#4E9A06"> code               </font>|<font color="#4E9A06"> original_method </font>|<font color="#4E9A06"> plugin_method_type </font>|<font color="#4E9A06"> instance                                         </font>|<font color="#4E9A06"> method_exists </font>|
+--------------------+-----------------+--------------------+--------------------------------------------------+---------------+
| order_cancellation | placeOrder      | around             | PayPal\Braintree\Plugin\OrderCancellation        | method is ok  |
| order_update       | placeOrder      | before             | Magento\PaymentServicesPaypal\Plugin\OrderUpdate | method is ok  |
+--------------------+-----------------+--------------------+--------------------------------------------------+---------------+
<font color="#8AE234"><u style="text-decoration-style:single"><b>Plugins for type Magento\Quote\Model\QuoteManagement</b></u></font>

+----------------------------------+-----------------+--------------------+------------------------------------------------------------+---------------+
|<font color="#4E9A06"> code                             </font>|<font color="#4E9A06"> original_method </font>|<font color="#4E9A06"> plugin_method_type </font>|<font color="#4E9A06"> instance                                                   </font>|<font color="#4E9A06"> method_exists </font>|
+----------------------------------+-----------------+--------------------+------------------------------------------------------------+---------------+
| update_bundle_quote_item_options | submit          | before             | Magento\Bundle\Plugin\Quote\UpdateBundleQuoteItemOptions   | method is ok  |
| validate_purchase_order_number   | submit          | before             | Magento\OfflinePayments\Plugin\ValidatePurchaseOrderNumber | method is ok  |
| coupon_uses_increment_plugin     | submit          | around             | Magento\SalesRule\Plugin\CouponUsagesIncrement             | method is ok  |
+----------------------------------+-----------------+--------------------+------------------------------------------------------------+---------------+

<font color="#EF2929"><u style="text-decoration-style:single"><b> ----- END Plugins for Scope ------global</b></u></font>
-- No specific scoped plugins injected for Magento\Quote\Api\CartManagementInterfaces in frontend --
-- No specific scoped plugins injected for Magento\Quote\Api\CartManagementInterfaces in adminhtml --
-- No specific scoped plugins injected for Magento\Quote\Api\CartManagementInterfaces in webapi_rest --
-- No specific scoped plugins injected for Magento\Quote\Api\CartManagementInterfaces in graphql --
</pre>



When you find plugins for an interface 'Magento\Quote\Api\CartManagementInterface', it will show plugins that are injected into the interface itself and a preference concrete class 'Magento\Quote\Model\QuoteManagement'. 

### 2. List all observers for an event 
```
bin/magento beta_dev:show_observers -e catalog_product_get_final_price 
```
```
Usage:
  beta_dev:show_observers [options]

Options:
  -e, --event=EVENT              Event code : --e catalog_product_get_final_price
  -s, --scope_code[=SCOPE_CODE]  Scope : -s global|frontend|adminhtml|crontab|webapi_rest|webapi_soap|graphql
```

![image](https://user-images.githubusercontent.com/820411/140700694-3d79bcc3-cbbb-4ecf-8d04-f31f8c653b72.png)

### 3. Show all controller actions per a scope 
```
bin/magento beta_dev:show_controller_action  -f checkout -a frontend
```

```
Description:
  Show all controller actions per a scope

Usage:
  beta_dev:show_controller_action [options]

Options:
  -f, --frontname=FRONTNAME  Frontname : --m catalog
  -a, --area=AREA            Area Code: --a frontend|adminhtml 
```
Example Result:

![image](https://user-images.githubusercontent.com/820411/141668634-744cf4a5-4322-461d-9705-508ebae0bb8f.png)

### 4. Reflection for Api Services

```
Description:
  Refection for API Services

Usage:
  beta_dev:api_reflection [options]

Options:
  -a, --api=API                    API ID : -a : /V1/carts/:cartId/shipping-information 
  -m, --http_method[=HTTP_METHOD]  HTTP Method: -m POST 

```
Example :
```
bin/magento beta_dev:api_reflection --api "/V1/carts/:cartId/shipping-information" -m POST
```
##### General information
![image](https://user-images.githubusercontent.com/820411/142757603-e20ba6e8-f950-4a13-8bb4-c4646787b271.png)
##### Input Data Reflection
![image](https://user-images.githubusercontent.com/820411/142757393-25dd2ae0-d06a-4319-8e7c-937aaaf6b3d1.png)
##### Output Data Reflection
![image](https://user-images.githubusercontent.com/820411/142757398-e75dda1a-c441-4be0-b1d0-458a315e80f2.png)



## Deploy static files ( Javascript, Css, Html, Js-translation, requirejs-config.js )
### Before running CLI commands below - Pls ensure you have deleted the folder var/view_proccessed ( Some static files are cached in that folder)

<pre>bin/magento   beta_dev:deploy_static -h
<font color="#C4A000">Description:</font>
  Deploy a static file.
  ---&gt; Deploy static files from a/an module/extension.
  E.g. bin/magento beta_dev:deploy_static -f js/view/shipping-address/address-renderer/default.js -t Magento/luna -m Magento_Checkout
  ---&gt; Deploy Css files base on a theme base Magento-Luna architecure.
  E.g. bin/magento beta_dev:deploy_static -f css/styles-l.css -t Magento/luna
  E.g. bin/magento beta_dev:deploy_static -f css/styles-m.css -t Magento/luna
  ---&gt; Deploy JS Translation Json File base on a theme base Magento-Luna architecure.
  E.g. bin/magento   beta_dev:deploy_static -t Magento/blank -f js-translation.json

<font color="#C4A000">Usage:</font>
  beta_dev:deploy_static [options]

<font color="#C4A000">Options:</font>
  <font color="#4E9A06">-f, --file_path=FILE_PATH</font>        File Path/ Directory Path: for specific file -f js/view/shipping-address/address-renderer/default.js OR for a specific folder -f js
  <font color="#4E9A06">-t, --theme_path=THEME_PATH</font>      Theme path: -t Magento/luna
  <font color="#4E9A06">-a, --area=AREA</font>                  Area Code: -a frontend|adminhtml
  <font color="#4E9A06">-m, --module_name[=MODULE_NAME]</font>  Module name: -m Magento_Checkout
  <font color="#4E9A06">-l, --locale_code[=LOCALE_CODE]</font>  Locale code: -l da_DK 
</pre>

### 1. Build/Deploy CSS/Javascript Files COME FROM an/a extension/module
```
bin/magento beta_dev:deploy_static -m Magento_Checkout -f js/view/shipping-address/address-renderer/default.js -t Magento/luna
```
-- Able to deploy Adminhtml themes and fully deploy for a directory
```
bin/magento beta_dev:deploy_static -t Magento/backend -a adminhtml -m Magento_Catalog -f js
```

### 2. Deploy a js-translation.json 
Example:
```
bin/magento   beta_dev:deploy_static-t Magento/luna -f js-translation.json
```
### 3. Deploy css/styles-m.css, css/styles-l.css
```
bin/magento   beta_dev:deploy_static-t Magento/luna -f css/styles-l.css
```
```
bin/magento   beta_dev:deploy_static -t Magento/luna -f css/styles-m.css
```



## RequireJS Config
### Deploy a requirejs-config.js 
Example:
```
bin/magento  beta_dev:deploy_requirejs -t Magento/luna
```

### I love coding 
```
@Trung,lv - skype: beta.trunglv@outlook.com - email : luuvantrung@gmail.com
```

