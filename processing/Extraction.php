<?php


namespace submitShow;

/**
 * Tools to extract a show from linear output recordings by stitching together chunked recording files
 * then trimming the file down to the show's start and end.
 */
class Extraction {
    // TODO Move this into config file
    private static $recordingUnit = 3600;
    // without trailing slash
    private static $recordingDirectory = "";
    private array $config;
    
    public function __construct($id = null) {
        $this->config = require "config.php";
    }
    
    /**
     * Finds stream recording block file names which span the given timeframe.
     * If you asked for 1:45pm-3:45pm and each block file was an hour long and starting on the hour, you'd be given 1pm, 2pm 
     * and 3pm's files.
     * 
     * @param string $startTime Wall clock time to start getting blocks from, parseable by strtotime.
     * @param string $endTime Wall clock time to of the final block, parseable by strtotime.
     * @return array  Array of file names for the recording blocks which cover this time (could be empty).
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
                    array_push($relevantFiles, Extraction::$recordingDirectory . "/" . $file);
                }
            }
        }
        
        return $relevantFiles;
    }

    /**
     * Stitches together recording blocks to give one audio file over the specified times. The file will probably be longer
     * than the specified times: if you asked for 1:45pm-3:45pm and each block file was an hour long and starting on the hour, 
     * you'd be given a file running from 1pm-3pm.
     *
     * @param string $startTime Wall clock time to start stitching from, parseable by strtotime.
     * @param string $endTime Wall clock time to end stitching at, parseable by strtotime.
     *
     * @return string  Path to the stitched file, or "" if no blocks existed.
     */
    public function stitch(string $startTime, string $endTime): string {
        $blocks = $this->getBlocks($startTime, $endTime);
        
        if (sizeof($blocks) > 0) {
            $blockList = ""; 
            foreach ($blocks as &$block) {
                $blockList .= "file '" . $block . "'\n";
            }
            
            $explodedFirstFileName = explode(".", $blocks[0]);
            $audioExtension        = end($explodedFirstFileName);
            
            $id = uniqid();
            $blockListFilePath = $this->config["tempDirectory"] . "/" . $id . ".list";
            $stitchedFilePath  = "../stitched/" . $id . "." . $audioExtension;
            
            mkdir("../stitched");
            
            file_put_contents($blockListFilePath, $blockList);
            // TODO Error handling
            shell_exec("ffmpeg -y -hide_banner -loglevel error -f concat -safe 0 -i \"$blockListFilePath\" -c copy \"$stitchedFilePath\"");
            unlink($blockListFilePath);
            
            return $stitchedFilePath;
        } else {
            return "";
        }
    }
    
    /**
     * Trims a previously-stitched audio file to the given start time and duration, with a fade on each end.
     * @param int $start The number of seconds into the file to start.
     * @param int $duration The duration of the resultant file in seconds.
     * @param string $fileName The name of the file to trim, including extension. The file will be pulled out of the 
     *                         `../stitched` directory.
     * @return string  The name of the trimmed file, which will be in the holding directory.
     */
    public function trim(int $start, int $duration, string $fileName): string {
        $filePath         = "../stitched/" . $fileName;
        $explodedFileName = explode(".", $fileName);
        $id               = $explodedFileName[0];
        $audioExtension   = end($explodedFileName);
        $outputFileName   = $id . "." . $audioExtension;
        $outputFilePath   = $this->config["holdingDirectory"] . "/" . $outputFileName;
        $fadeDuration     = 2;
        $fadeOutAt        = $duration - $fadeDuration;
        
        set_time_limit(120);
        // TODO Error handling
        shell_exec("ffmpeg -y -hide_banner -loglevel error -ss \"$start\" -i \"$filePath\" -t \"$duration\" -af afade=in:0:d=$fadeDuration,afade=out:st=$fadeOutAt:d=$fadeDuration \"$outputFilePath\"");
        
        return $outputFileName;
    }
}