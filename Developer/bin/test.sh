php bin/magento beta_dev:check_di -t "Magento\Quote\Model\QuoteManagement"
php bin/magento beta_dev:show_plugins_by_listener_type -t around
php bin/magento beta_dev:show_plugins -t 'Magento\Quote\Api\CartManagementInterface'
php bin/magento beta_dev:show_observers -e catalog_product_get_final_price
php bin/magento beta_dev:show_controller_action  -f checkout -a frontend
php bin/magento beta_dev:api_reflection --api "/V1/carts/:cartId/shipping-information" -m POST
php bin/magento beta_dev:deploy_static -f js/view/shipping-address/address-renderer/default.js -t Magento/blank -m Magento_Checkout
php bin/magento beta_dev:deploy_static -f css/styles-l.css -t Magento/blank
php bin/magento   beta_dev:deploy_static -t Magento/blank -f js-translation.json
php bin/magento  beta_dev:deploy_requirejs -t Magento/blank