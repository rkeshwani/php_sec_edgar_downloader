<?php
require_once __DIR__ . '/vendor/autoload.php';

namespace jadchaar\secEdgarDownloader\utils;


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
                //Check if root_cause exists in error message body
                $error_msg = $e->getMessage();
                $error_msg_json = json_decode($error_msg, true);
                if (isset($error_msg_json['error']['root_cause'])) {
                    $root_cause = $error_msg_json['error']['root_cause'];
                    if (count($root_cause) > 0) {
                        $error_reason = $root_cause[0]['reason'];
                        throw new \Exception("Edgar Search API encountered an error: " . $error_reason . ". Request payload:\n" . json_encode($payload));
                    }
                }
            }
            $response_body = $response->getBody();
            $response_body_json = json_decode($response_body, true);
            $query_hits = $response_body_json["hits"]["hits"];
            // No more results to process
            if (empty($query_hits)) {
                break;
            }

            foreach ($query_hits as $hit) {
                $hit_filing_type = $hit["_source"]["file_type"];

                $is_amend = substr($hit_filing_type, -2) == "/A";
                if (!$include_amends && $is_amend) {
                    continue;
                }

                // Work around bug where incorrect filings are sometimes included.
                // For example, AAPL 8-K searches include N-Q entries.
                if (!$is_amend && $hit_filing_type != $filing_type) {
                    continue;
                }

                $metadata = Utils::build_filing_metadata_from_hit($hit);
                $filings_to_fetch[] = $metadata;

                if (count($filings_to_fetch) == $num_filings_to_download) {
                    return $filings_to_fetch;
                }
            }
            // Edgar queries 100 entries at a time, but it is best to set this
            // from the response payload in case it changes in the future
            $query_size = $response_body_json["query"]["size"];
            $start_index += $query_size;

            // Prevent rate limiting
            sleep(Constants::SEC_EDGAR_RATE_LIMIT_SLEEP_INTERVAL);
        }
        return $filings_to_fetch;
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
    private static function build_filing_metadata_from_hit($hit)
    {
        list($accession_number, $filing_details_filename) = explode(":", $hit["_id"], 2);
        // the CIKs of executives carrying out insider transactions like in form 4.
        $cik = $hit["_source"]["ciks"][count($hit["_source"]["ciks"]) - 1];
        $accession_number_no_dashes = str_replace("-", "", $accession_number, 2);

        $submission_base_url = Constants::SEC_EDGAR_ARCHIVES_BASE_URL . "/" . $cik . "/" . $accession_number_no_dashes;

        $full_submission_url = $submission_base_url . "/" . $accession_number . ".txt";

        // Get XSL if human readable is wanted
        // XSL is required to download the human-readable
        // and styled version of XML documents like form 4
        // SEC_EDGAR_ARCHIVES_BASE_URL + /320193/000032019320000066/wf-form4_159839550969947.xml
        // SEC_EDGAR_ARCHIVES_BASE_URL +
        //           /320193/000032019320000066/xslF345X03/wf-form4_159839550969947.xml

        // $xsl = $hit["_source"]["xsl"];
        // if ($xsl !== null) {
        //     $filing_details_url = $submission_base_url . "/" . $xsl . "/" . $filing_details_filename;
        // } else {
        //     $filing_details_url = $submission_base_url . "/" . $filing_details_filename;
        // }

        $filing_details_url = $submission_base_url . "/" . $filing_details_filename;

        $filing_details_filename_extension = pathinfo($filing_details_filename, PATHINFO_EXTENSION);
        $filing_details_filename = Constants::FILING_DETAILS_FILENAME_STEM . $filing_details_filename_extension;

        return new FilingMetadata(
            $accession_number,
            $full_submission_url,
            $filing_details_url,
            $filing_details_filename
        );
    }
}

// Object for storing metadata about filings that will be downloaded.
class FilingMetadata {
    public $accession_number;
    public $full_submission_url;
    public $filing_details_url;
    public $filing_details_filename;

    public function __construct($accession_number, $full_submission_url, $filing_details_url, $filing_details_filename) {
        $this->accession_number = $accession_number;
        $this->full_submission_url = $full_submission_url;
        $this->filing_details_url = $filing_details_url;
        $this->filing_details_filename = $filing_details_filename;
    }
}
