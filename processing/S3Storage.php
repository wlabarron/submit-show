<?php


use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

class S3Storage extends Storage {
    private S3Client $s3Client;
    private array    $config;

    /**
     * @inheritDoc
     */
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        if (empty($this->config["s3Endpoint"]))  throw new Exception("No S3 endpoint specified.");
        if (empty($this->config["s3Bucket"]))    throw new Exception("No S3 bucket specified.");
        if (empty($this->config["s3AccessKey"])) throw new Exception("No S3 access key specified.");
        if (empty($this->config["s3Secret"]))    throw new Exception("No S3 secret specified.");

        try {
            // create S3 client
            $this->s3Client = new S3Client([
                'endpoint'    => $this->config["s3Endpoint"],
                'region'      => $this->config["s3Region"],
                'version'     => 'latest',
                'credentials' => [
                    'key'    => $this->config["s3AccessKey"],
                    'secret' => $this->config["s3Secret"],
                ],
            ]);
        } catch (S3Exception $e) {
            error_log($e->getMessage());
            throw new Exception("S3 Client creation failed.");
        }
    }

    /**
     * @inheritDoc
     */
    public function offload(string $file) {
        try {
            $this->s3Client->putObject([
                'Bucket'     => $this->config["s3Bucket"],
                'Key'        => $file,
                'SourceFile' => $this->config["waitingUploadsFolder"] . "/" . $file
            ]);

            // remove the waiting show from local storage
            unlink($this->config["waitingUploadsFolder"] . "/" . $file);

            error_log($file . " sent to S3.");
        } catch (S3Exception $e) {
            error_log($e->getMessage());
            throw new Exception("Couldn't send file to S3.");
        }
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $file): string {
        try {
            $this->s3Client->getObject(array(
                'Bucket' => $this->config["s3Bucket"],
                'Key' => $file,
                'SaveAs' => $this->config["tempDirectory"] . "/" . $file
            ));

            return $this->config["tempDirectory"] . "/" . $file;
        } catch (S3Exception $e) {
            error_log($e->getMessage());
            throw new Exception("Couldn't retrieve file from S3.");
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $file) {
        try {
            $this->config->deleteObject(array(
                'Bucket' => $this->config["s3Bucket"],
                'Key'    => $file
            ));
        } catch (S3Exception $e) {
            error_log($e->getMessage());
            throw new Exception("Couldn't delete file from S3.");
        }
    }
}