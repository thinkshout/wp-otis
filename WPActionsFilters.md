# WP-OTIS Actions and Filters Available
These actions and filters are available for use in your theme or plugin.


## Actions

## Before Fetch Listings
`wp_otis_before_fetch_listings`
```php
do_action( 'wp_otis_before_fetch_listings', $assoc_args );
```
This action is fired before each fetch of listings from the OTIS API. The listings are fetched in pages based on results from OTIS and the page is set by the `page` argument. These fetches are stored in a transient for use in the process listings step.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## After Fetch Listings
`wp_otis_after_fetch_listings`
```php
do_action( 'wp_otis_after_fetch_listings', $assoc_args );
```
This action is fired after the last fetch of listings from the OTIS API.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## Before Process Listings
`wp_otis_before_process_listings`
```php
do_action( 'wp_otis_before_process_listings', $assoc_args );
```
This action is fired before each page of listings is processed. The listings are processed in chunks of 100 and the page is determined by the `modified_process_page` argument.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## After Process Listings
`wp_otis_after_process_listings`
```php
do_action( 'wp_otis_after_process_listings', $assoc_args );
```
This action is fired after the last page of listings is processed.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## Before Delete Removed Listings
`wp_otis_before_delete_removed_listings`
```php
do_action( 'wp_otis_before_delete_removed_listings', $assoc_args );
```
This action is fired before each page of listings is deleted. The listings are deleted in chunks based on OTIS results and the page is determined by the `deletes_page` argument.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## After Delete Removed Listings
`wp_otis_after_delete_removed_listings`
```php
do_action( 'wp_otis_after_delete_removed_listings', $assoc_args );
```
This action is fired after the last page of listings is deleted.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## Before Sync All Listings
`wp_otis_before_sync_all_listings`
```php
do_action( 'wp_otis_before_sync_all_listings', $assoc_args );
```
This action is fired before each page of listings is fetched from OTIS. The listings page is determined by the `sync_page` argument.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## After Sync All Listings
`wp_otis_after_sync_all_listings`
```php
do_action( 'wp_otis_after_sync_all_listings', $assoc_args );
```
This action is fired after the last page of listings is fetched from OTIS.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## Before Process Active Listings
`wp_otis_before_process_active_listings`
```php
do_action( 'wp_otis_before_process_active_listings', $assoc_args );
```
This action is fired before each page of active listings is processed. The listings are processed in chunks of 100 and the page is determined by the `process_page` argument.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## After Process Active Listings
`wp_otis_after_process_active_listings`
```php
do_action( 'wp_otis_after_process_active_listings', $assoc_args );
```
This action is fired after the last page of active listings is processed.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## Before Import Active Listings
`wp_otis_before_import_active_listings`
```php
do_action( 'wp_otis_before_import_active_listings', $assoc_args );
```
This action is fired before each page of active listings is imported. The listings are imported in chunks of 100 and the page is determined by the `import_page` argument.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## After Import Active Listings
`wp_otis_after_import_active_listings`
```php
do_action( 'wp_otis_after_import_active_listings', $assoc_args );
```
This action is fired after the last page of active listings is imported.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS function.


## Cancel Import
`wp_otis_cancel_import`
```php
do_action( 'wp_otis_cancel_import' );
```
This action is fired when the import is cancelled before cancel actions are taken.


## Filters

## WP-OTIS Listings Args
`wp_otis_listings`
```php
apply_filters( 'wp_otis_listings', $assoc_args );
```
This filter is used to modify the arguments passed to the WP-OTIS importer function. Expected to return an array of arguments.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS Importer function.


## WP-OTIS Listings API Params
`wp_otis_listings_api_params`
```php
apply_filters( 'wp_otis_listings_api_params', $assoc_args );
```
This filter is used to modify the arguments passed to the OTIS API in the fetch listings function used in modified POI syncing. Expected to return an array of arguments.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS Importer function.


## WP-OTIS Listings Before Process Args
`wp_otis_before_process_listings_args`
```php
apply_filters( 'wp_otis_before_process_listings_args', $assoc_args );
```
This filter is used to modify the arguments passed to the WP-OTIS Importer function before processing listings. Expected to return an array of arguments.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS Importer function.


## WP-OTIS Listings To Process
`wp_otis_listings_to_process`
```php
apply_filters( 'wp_otis_listings_to_process', $listings_chunks[ $listings_page - 1 ], $listings_type );
```
This filter is used to modify the chunk of listings to be processed. Expected to return an array of listings to process.
### Parameters
`$listings_chunk` - The chunk of listings to process.
`$listings_type` - The type of listings being processed.


## WP-OTIS Before Delete Removed Listings Args
`wp_otis_before_delete_removed_listings_args`
```php
apply_filters( 'wp_otis_before_delete_removed_listings_args', $assoc_args );
```
This filter is used to modify the arguments passed to the WP-OTIS Importer function before deleting removed listings. Expected to return an array of arguments.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS Importer function.


## WP-OTIS Before Sync All Listings Args
`wp_otis_before_sync_all_listings_args`
```php
apply_filters( 'wp_otis_before_sync_all_listings_args', $assoc_args );
```
This filter is used to modify the arguments passed to the WP-OTIS Importer function before syncing all listings. Expected to return an array of arguments.
### Parameters
`$assoc_args` - The arguments passed to the WP-OTIS Importer function.


## WP-OTIS Number of POIs Processed Per Batch
`wp_otis_processing_chunk_size`
```php
apply_filters( 'wp_otis_processing_chunk_size', $number_of_posts );
```
### Parameters
`$number_of_posts` - The number of POIs to be processed at a time, defaults to 10 POIs.
