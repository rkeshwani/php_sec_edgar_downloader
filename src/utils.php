<?php

namespace jadchaar\secEdgarDownloader\utils;

require_once __DIR__ . '/vendor/autoload.php';

use Exception;
use jadchaar\secEdgarDownloader\constants\Constants;

class Utils
{
    /**
     * Checks if the given string is a valid CIK (Central Index Key).
     *
     * @param string $ticker_or_cik The ticker or CIK to check.
     * @return bool True if the string is a valid CIK, false otherwise.
     */
    public static function is_cik(string $ticker_or_cik)
    {
        //parse cik to check if it is a number
        if (is_numeric($ticker_or_cik)) {
            return true;
        } else {
            return false;
        }
    }
    public static function validate_date_format(string $date_format)
    {
        $error_msg_base = "Please enter a date string of the form YYYY-MM-DD.";
        if (!is_string($date_format)) {
            throw new \TypeError($error_msg_base);
        }
        try {
            \DateTime::createFromFormat(Constants::DATE_FORMAT_TOKENS, $date_format);
        } catch (\Exception $e) {
            throw new \Exception("Incorrect date format. " . $error_msg_base);
        }
    }
    public static function get_filing_urls_to_download(
        string $filing_type,
        string $ticker_or_cik,
        int $num_filings_to_download,
        string $before_date,
        string $after_date,
        bool $include_amends,
        string $query,
        string $user_agent
    ) {
        $filings_to_fetch = [];
        $start_index = 0;
        while (count($filings_to_fetch) < $num_filings_to_download) {
            $payload = Utils::form_request_payload($ticker_or_cik, [$filing_type], $after_date, $before_date, $start_index, $query);
            $headers = [
                "User-Agent" => $user_agent,
                "Accept-Encoding" => "gzip, deflate",
                "Host" => "efts.sec.gov",
            ];
            $client = \GuzzleHttp\Client();
            try {
                $response = $client->post(Constants::SEC_EDGAR_SEARCH_API_ENDPOINT, ['json' => $payload, 'headers' => $headers]);
            } catch (Exception $e) {
            }
        }
    }
    private static function form_request_payload(
        $ticker_or_cik,
        $filing_types,
        $start_date,
        $end_date,
        $start_index,
        $query
    ) {
        $payload = array(
            "dateRange" => "custom",
            "startdt" => $start_date,
            "enddt" => $end_date,
            "entityName" => $ticker_or_cik,
            "forms" => $filing_types,
            "from" => $start_index,
            "q" => $query
        );
        return $payload;
    }
}
