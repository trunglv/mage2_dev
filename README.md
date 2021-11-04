# mage2_dev
Quick tools for Magento 2 Development and Deployment:

I think, This extension/project will help developers save time in developement and deployment in any Magento2 project.

#### We can enable a production mode on a local machine host, or DEV server (For saving time loading), And then just put only updated files to a pub/static folder.
#### For example, you want to test a mixin you are implementing, just use a few commands.

- Push mixin configuration in a requirejs-config.js of your module.
```
   bin/magento  beta_dev:deploy_requirejs -t Magento/luna
```
- Deploy your mixin file to pub/static folder
```

bin/magento beta_dev:deploy_static -m [your_module] -f js/[your_mixin_path] -t [your_theme]

```

## Deploy static files ( Javascript, Css, Html, Js-translation, requirejs-config.js )
### 1. Build/Deploy CSS/Javascript Files
```
bin/magento beta_dev:deploy_static -m Magento_Checkout -f js/view/shipping-address/address-renderer/default.js -t Magento/luna
```

### 2. Deploy a requirejs-config.js 
Example:
```
bin/magento  beta_dev:deploy_requirejs -t Magento/luna
```

### 3. Deploy a js-translation.json 
Example:
```
bin/magento  beta_dev:deploy_requirejs -f Magento/luna
```

## Other tools

### 1. Build an order grid table ( For missing orders and missing data in existed items ) 
Example:
#### Build for all missing orders 
```
sudo bin/magento beta_dev:build_order_grid --missing-orders true

```
#### Build for a specific order -- In case you want to refresh just one item due to just missing a few fields. 

```
sudo bin/magento beta_dev:build_order_grid --missing-orders [order_id]

```
