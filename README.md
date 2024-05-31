[![Latest Stable Version](https://poser.pugx.org/paradoxlabs/pagebuilder-widgets/v/stable)](https://packagist.org/packages/paradoxlabs/pagebuilder-widgets)
[![License](https://poser.pugx.org/paradoxlabs/pagebuilder-widgets/license)](https://packagist.org/packages/paradoxlabs/pagebuilder-widgets)
[![Total Downloads](https://poser.pugx.org/paradoxlabs/pagebuilder-widgets/downloads)](https://packagist.org/packages/paradoxlabs/pagebuilder-widgets)

<p align="center">
    <a href="https://www.paradoxlabs.com"><img alt="ParadoxLabs" src="https://paradoxlabs.com/wp-content/uploads/2020/02/pl-logo-canva-2.png" width="250"></a>
</p>

This module extends Magento Page Builder with additional widgets.

Requirements
============

* Magento 2.4 (or equivalent version of Adobe Commerce, Adobe Commerce Cloud, or Mage-OS)
* PHP 7.4, 8.0, 8.1, or 8.2
* composer 2

Features
========

## Categories content type

Use this Categories content type to add a grid or carousel of categories to any Page Builder content area. This includes CMS pages, CMS blocks, description fields, and more.

### Categories block in Page Builder
<img width="901" alt="" src="https://github.com/ParadoxLabs-Inc/pagebuilder-widgets/assets/13335952/243b7edf-0203-4aea-9788-70b315b5cf5a">

### Categories block settings
<img width="895" alt="" src="https://github.com/ParadoxLabs-Inc/pagebuilder-widgets/assets/13335952/4dc1737d-d974-48aa-a871-9fe29e2ed2ca">

### Categories grid on the frontend
<img width="902" alt="" src="https://github.com/ParadoxLabs-Inc/pagebuilder-widgets/assets/13335952/3f38f166-7955-44b4-a642-5a635156b3d7">

## Possible Future Development

* Accordion layout
* Carousel layout with arbitrary content blocks
* 'Scheduled Content' layout that automatically shows/hides the content within it based on date/time (client-side)

Installation and Usage
======================

In SSH at your Magento base directory, run:

    composer require paradoxlabs/pagebuilder-widgets
    php bin/magento module:enable ParadoxLabs_PageBuilderWidgets
    php bin/magento setup:upgrade

## Applying Updates

In SSH at your Magento base directory, run:

    composer update paradoxlabs/pagebuilder-widgets
    php bin/magento setup:upgrade

Changelog
=========

Please see [CHANGELOG.md](https://github.com/ParadoxLabs-Inc/pagebuilder-widgets/blob/master/CHANGELOG.md).

Support
=======

This module is provided free and without support of any kind. You may report issues you've found in the module, and we
will address them as we are able, but **no support will be provided here.**

If you need personal support services, please contact [support.paradoxlabs.com](https://support.paradoxlabs.com).

Contributing
============

Please feel free to submit pull requests with any contributions. We welcome and appreciate your support, and will
acknowledge contributors.

This module is maintained by ParadoxLabs, a Magento solutions provider.

License
=======

This module is licensed
under [APACHE LICENSE, VERSION 2.0](https://github.com/ParadoxLabs-Inc/pagebuilder-widgets/blob/master/LICENSE).
