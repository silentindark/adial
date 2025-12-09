<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'dashboard';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// =========================================================================
// API Routes v1
// =========================================================================

// API Index
$route['api'] = 'api/index';
$route['api/index'] = 'api/index';

// Campaign Routes
$route['api/campaigns'] = 'api/campaigns_list';
$route['api/campaigns/(:num)'] = 'api/campaigns_view/$1';
$route['api/campaigns/(:num)/stats'] = 'api/campaigns_stats/$1';
$route['api/campaigns/(:num)/start'] = 'api/campaigns_start/$1';
$route['api/campaigns/(:num)/stop'] = 'api/campaigns_stop/$1';
$route['api/campaigns/(:num)/pause'] = 'api/campaigns_pause/$1';
$route['api/campaigns/(:num)/resume'] = 'api/campaigns_resume/$1';

// Campaign Numbers Routes
$route['api/campaigns/(:num)/numbers'] = 'api/numbers_list/$1';
$route['api/campaigns/(:num)/numbers/bulk'] = 'api/numbers_bulk_add/$1';
$route['api/numbers/(:num)'] = 'api/numbers_view/$1';

// CDR Routes
$route['api/cdr'] = 'api/cdr_list';
$route['api/cdr/stats'] = 'api/cdr_stats';
$route['api/cdr/(:num)'] = 'api/cdr_view/$1';

// Monitoring Routes
$route['api/monitoring/status'] = 'api/monitoring_status';
$route['api/monitoring/channels'] = 'api/monitoring_channels';
$route['api/monitoring/realtime'] = 'api/monitoring_realtime';

// IVR Routes
$route['api/ivr'] = 'api/ivr_list';
$route['api/ivr/(:num)'] = 'api/ivr_view/$1';

// User Management Routes (Admin only)
$route['api/users'] = 'api/users_list';
$route['api/users/(:num)'] = 'api/users_update/$1';

// API Token Management Routes
$route['api/tokens'] = 'api/tokens_list';
$route['api/tokens/(:num)'] = 'api/tokens_revoke/$1';
