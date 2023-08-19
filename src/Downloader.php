<?php

namespace jadchaar\secEdgarDownloader;

require_once __DIR__ . '/../vendor/autoload.php';

use jadchaar\secEdgarDownloader\utils\Utils;
use jadchaar\secEdgarDownloader\constants\Constants;


class Downloader
{
    private $download_folder;
    public function __construct($download_folder = null)
    {
        if ($download_folder == null) {
            //set to current working dieectory
            $this->download_folder = getcwd();
        } else {
            $this->download_folder = $download_folder;
        }
    }

    public function get(
        string $filing,
        string $ticker_or_cik,
        int $amount,
        string $before_date = null,
        string $after_date = null,
        bool $include_amends = false,
        bool $download_details = true,
        string $query = "",
        string $user_agent
    ) {
        //Check if symbol or CIK is not null or blank and is a string
        if ($ticker_or_cik == null || $ticker_or_cik == '' || !is_string($ticker_or_cik)) {
            throw new \Exception("Invalid ticker or CIK. Please enter a non-blank value as a string.");
        }
        //Remove whitespace and convert to uppercase
        $ticker_or_cik = strtoupper(trim($ticker_or_cik));
        if (Utils::is_cik($ticker_or_cik)) {
            //If length of CIK is less than 10, pad with 0s 
            if (strlen($ticker_or_cik) < 10) {
                $ticker_or_cik = str_pad($ticker_or_cik, 10, "0", STR_PAD_LEFT);
            }
            // If length of CIK is greater than 10, throw exception
            else if (strlen($ticker_or_cik) > 10) {
                throw new \Exception("Invalid CIK. Please enter a valid CIK.");
            }
        }
        //Check if the amount is set, if not set it to a really large number
        if ($amount == null) {
            $amount = 1000000;
        } else {
            //Convert the amount into a integer and if it is less than 1 throw an exception
            $amount = intval($amount);
            if ($amount < 1) {
                throw new \Exception("Invalid amount. Please enter a valid amount.");
            }
        }
        // SEC allows for searching after a date, but not before a date if it is null set it to that date otherwise throw an exception if it is before 2000-01-01
        if ($after_date == null) {
            $after_date = Constants::DEFAULT_AFTER_DATE;
        } else {
            Utils::validate_date_format($after_date);
            if (intval($after_date) < Constants::DEFAULT_AFTER_DATE) {
                throw new \Exception("Invalid before_date. Please enter a valid after_date.");
            }
        }

        if ($before_date == null) {
            $before_date = Constants::getDefaultBeforeDate();
        } else {
            Utils::validate_date_format($before_date);
        }
        if ($after_date > $before_date) {
            throw new \Exception(
                "Invalid after and before date combination. "
                    . "Please enter an after date that is less than the before date."
            );
        }
        //Check if the filing is in the supported filings array
        if (!in_array($filing, Constants::SUPPORTED_FILINGS)) {
            throw new \Exception("Invalid filing. Please enter a valid filing.");
        }
        // Check if query is a string
        if (!is_string($query)) {
            throw new \TypeError("Invalid query. Please enter a valid query.");
        }

        $filings_to_fetch = 
            Utils::get_filing_urls_to_download(
                $filing,
                $ticker_or_cik,
                $amount,
                $before_date,
                $after_date,
                $include_amends,
                $query,
                $user_agent
            );
        // Download the filings
        Utils::download_filings(
            $this->download_folder,
            $ticker_or_cik,
            $filing,
            $filings_to_fetch,
            $download_details,
            $user_agent
        );
        # Get number of unique accession numbers downloaded
        return Utils::get_number_of_unique_filings($filings_to_fetch);
    }
}
