# mage2_dev
## Quick tools for Magento 2 Development and Deployment:

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

