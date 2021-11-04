# mage2_dev
Quick tools for Magento 2 Development and Deployment:

## Deployment static files ( Javascript, Css, Js-translation, requirejs-config.js )
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

## Other tools to fix 
### 1. Build a order grid table ( For missing orders and missing data in existed items ) 
Example:
#### Build for all missing orders 
```
sudo bin/magento beta_dev:build_order_grid --missing-orders true

```
#### Build for a specific order -- In case you want to refresh just one item due to it just misses few fields. 

```
sudo bin/magento beta_dev:build_order_grid --missing-orders [order_id]

```
