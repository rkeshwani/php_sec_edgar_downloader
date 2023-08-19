Ported over from python. Original Repo:
[https://github.com/jadchaar/sec-edgar-downloader/blob/master/sec_edgar_downloader]

## Installation

To add this library to your `composer.json` file, you can run the following command in your project directory:

`composer require jadchaar/sec_edgar_downloader`

Note your composer file will need to allow development level stability to use for now.

 ## Example Usage
```php
 use Jadchaar\SecEdgarDownloader\Downloader;
 $filepath = 'sec-documents/';
 $downloader = new Downloader($filepath);
 $filing = '10-K';
 $ticker_or_cik = 'AAPL';
 $amount = 10;
 $before_date = '2023-08-19';
 $after_date = '2021-01-01';
 $include_amends = true;
 $download_details = true;
 $query = '';
 $user_agent = 'Company Name employee@company.com';
 
 $num_filings = $downloader->get(
     $filing,
     $ticker_or_cik,
     $amount,
     $before_date,
     $after_date,
     $include_amends,
     $download_details,
     $query,
     $user_agent
 );
 
 echo "Number of unique filings downloaded: " . $num_filings;
```
 ## Todo List

 - [ ] Abstract HTTP client
 - [ ] Parameterize rate limit with default
 - [ ] Allow downloading different file types
 - [ ] Enable different file type paths besides local file systems

 
