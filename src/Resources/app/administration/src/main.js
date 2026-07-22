import './init/api-service.init';
import './module/inpost-order';
import './component/inpost-documentation-link';
import './component/inpost-consents-manager';

import enGB from './snippet/en-GB.json';
import plPL from './snippet/pl-PL.json';
import deDE from './snippet/de-DE.json';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('pl-PL', plPL);
Shopware.Locale.extend('de-DE', deDE);
