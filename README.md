# mage2_dev - CLI tools for Magento 2 Development and Deployment

## If you have any idea about a new stuff need to be added, pls contact me via email : luuvantrung@gmail.com or skype beta_trunglv! My pleasure!

I think, This project will help developers save time in developement and deployment in any Magento2 project.


#### We can enable a production mode on a local machine host, or DEV server (For saving time loading), And then just put only updated files to a pub/static folder.
#### For example, you want to test a mixin you are implementing, just use a few commands.

- Push mixin configuration into a requirejs-config.js of your module.
```
   bin/magento  beta_dev:deploy_requirejs -t Magento/luna
```
- Deploy your mixin file to pub/static folder
```

bin/magento beta_dev:deploy_static -m [your_module] -f js/[your_mixin_path] -t [your_theme]

```
## Installation

##### Clone and copy a source code into a folder app/code/Betagento 
##### Just enable this module and run DI Complile ( Magento developers will understand )

## Debug tools
### 1. List all plugins for a class
Example:
```
bin/magento beta_dev:show_plugins -t "Magento\InventorySales\Model\AreProductsSalableForRequestedQty"
```

Examples for results:

![image](https://user-images.githubusercontent.com/820411/140596064-b3299395-16fe-40ef-8b2b-fc4d00a9d2d6.png)


![image](https://user-images.githubusercontent.com/820411/140596086-56af8e1f-ba59-4a1c-86d5-c5afa4584480.png)

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

### 3. Show all controller actions for a frontname per a scope
```
bin/magento beta_dev:show_controller_action  -f checkout -a frontend
```

```
Description:
  Show all controller actions for a frontname per a scope

Usage:
  beta_dev:show_controller_action [options]

Options:
  -f, --frontname=FRONTNAME  Frontname : --m catalog
  -a, --area=AREA            Area Code: --a frontend|adminhtml 
```
Example Result:

![image](https://user-images.githubusercontent.com/820411/141668634-744cf4a5-4322-461d-9705-508ebae0bb8f.png)

### 4. Reflect Api Service

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
### Before run CLI commands below - Pls ensure you have deleted the folder var/view_proccessed ( Some static files are cached in that folder)
### 1. Build/Deploy CSS/Javascript Files
```
bin/magento beta_dev:deploy_static -m Magento_Checkout -f js/view/shipping-address/address-renderer/default.js -t Magento/luna
```
-- Able to deploy adminhtml themes and fully deploy for a directory
```
bin/magento beta_de:deploy_static -t Magento/backend -a adminhtml -m Magento_Catalog -f js
```

### 2. Deploy a requirejs-config.js 
Example:
```
bin/magento  beta_dev:deploy_requirejs -t Magento/luna
```

### 3. Deploy a js-translation.json 
Example:
```
bin/magento  beta_dev:deploy_requirejs -t Magento/luna
```
### 4. Deploy css/styles-m.css, css/styles-l.css
```
bin/magento  beta_dev:deploy_requirejs -t Magento/luna -f css/styles-l.css
```
```
bin/magento  beta_dev:deploy_requirejs -t Magento/luna -f css/styles-m.css
```

## Other tools

### 1. Build an order grid table ( For missing orders and missing data in existed items ) 
Example:
#### Build for all missing orders 
```
bin/magento beta_dev:build_order_grid --missing-orders true

```
#### Build for a specific order -- In case you want to refresh just one item due to just missing a few fields. 

```
bin/magento beta_dev:build_order_grid --missing-orders [order_id]

```
