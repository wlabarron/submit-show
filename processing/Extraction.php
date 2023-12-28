<?php


namespace submitShow;

/**
 * Tools to extract a show from linear output recordings by stitching together chunked recording files
 * then trimming the file down to the show's start and end.
 */
class Extraction {
    // TODO Move this into config file
    private static $recordingUnit = 3600;
    private static $recordingDirectory = "";
    private array $config;
    
    public function __construct() {
        $this->config = require "config.php";
    }
    
    /**
     * Returns an array of file names of stream recording files which span the given start and end time.
     * @param string $startTime Wall clock time to start the extraction from. The actual returned file will probably start 
     *                          before this time.
     * @param string $endTime Wall clock time to end the extraction from. The actual returned file will probably end 
      *                       after this time.
     * @return array  Array of file names for the recording blocks which cover this time. For example, if you asked for
     *                1:45pm-3:45pm and each block file was an hour long and starting on the hour, you'd be given 1pm, 2pm and
     *                3pm's files.
     */
    public function getBlocks(string $startTime, string $endTime): array {
        $allFiles      = scandir(Extraction::$recordingDirectory);
        $relevantFiles = array();
        
        $startTime = strtotime($startTime) - Extraction::$recordingUnit;
        $endTime   = strtotime($endTime);

        foreach ($allFiles as &$file) {
            if ($file !== "." && $file !== "..") {
                $fileDate = strtotime($file);
                if ($startTime <= $fileDate && $fileDate <= $endTime) {
                    array_push($relevantFiles, $file);
                }
            }
        }
        
        return $relevantFiles;
    }
}

$extraction = new Extraction();
echo(json_encode($extraction->getBlocks("20230615-054500", "20230615-071500")));