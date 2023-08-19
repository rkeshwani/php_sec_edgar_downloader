<?php
namespace jadchaar\secEdgarDownloader;
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
?>