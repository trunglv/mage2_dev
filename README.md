# mage2_dev
## Quick tools for Magento 2 Development and Deployment:
### 1. Buid/Deploy CSS/Javascript Files
Example command line: 
> bin/magento beta_dev:deploy_static --m Magento_Checkout --f js/view/shipping-address/address-renderer/default.js
### 2. Add timestamp number into the end of Css files -- For avoid browser cache -- helpful for urgent CSS change
> Add a file enable_time_in_url.flag into a folder pub/static
> 
> After doing clean cache then you will see a parametter ?t=[TIMESTAM] be added into every CSS files : /frontend/XXX/newer/en_US/css/styles.css?t=19383438448
