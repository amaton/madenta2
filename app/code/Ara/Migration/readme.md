1. Put this module under app/code/
2. Change credentials in app/code/Ara/Migration/Console/Command/CatalogMigration.php
3. php bin/magento cache:clean
4. php bin/magento module:enable Ara_Migration
5. php bin/magento migration:catalog
