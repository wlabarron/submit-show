<?php

namespace submitShow;

/**
 * Tools to extract a show from linear output recordings by stitching together chunked recording files
 * then trimming the file down to the show's start and end.
 */
class Extraction {
    private array $config;
    
    public function __construct() {
        $this->config = require "config.php";
    }
    
    /**
     * Checks if Extraction features are enabled and configured.
     * @return bool  true if enabled and configured, false otherwise.
     */
    public function isEnabled(): bool {
        if (isset($this->config["extraction"]) &&
            $this->config["extraction"]["enabled"] && 
            !empty($this->config["extraction"]["blocksDirectory"]) &&
            is_numeric($this->config["extraction"]["blockLength"]) &&
            is_numeric($this->config["extraction"]["fadeDuration"])) {
            return true;  
        } else {
            return false;
        }
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
    private function getBlocks(string $startTime, string $endTime): array {
        $allFiles      = scandir($this->config["extraction"]["blocksDirectory"]);
        $relevantFiles = array();
        
        $startTime = strtotime($startTime) - $this->config["extraction"]["blockLength"];
        $endTime   = strtotime($endTime);

        foreach ($allFiles as &$file) {
            if ($file !== "." && $file !== "..") {
                $fileDate = strtotime($file);
                if ($startTime <= $fileDate && $fileDate <= $endTime) {
                    array_push($relevantFiles, $this->config["extraction"]["blocksDirectory"] . "/" . $file);
                }
            }
        }
        
        return $relevantFiles;
    }

    /**
     * Stitches together recording blocks to give one audio file over the specified times.
     *
     * @param string $startTime Wall clock time to start stitching from, parseable by strtotime.
     * @param string $endTime Wall clock time to end stitching at, parseable by strtotime.
     *
     * @return array Name of stitched file (in the `../stitched` directory) or empty string on error.
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
            $stitchedFileName  = $id . "." . $audioExtension;
            $stitchedFilePath  = dirname(__FILE__) . "/../stitched/" . $stitchedFileName;
            
            if (!is_dir("../stitched")) {
                mkdir("../stitched");
            }
            
            // The blocks returned will probably span longer than the time requested. Calculate the difference between the start of the
            // first block and the start of the time range we want, then the duration of the time range we want.
            $firstBlockPathSplit = explode("/", $blocks[0]);
            $firstBlockFileName  = end($firstBlockPathSplit);
            $firstBlockStartTime = strtotime($firstBlockFileName);
            $wallClockStart      = strtotime($startTime);
            $wallClockEnd        = strtotime($endTime);
            $blockStartToRangeStart = $wallClockStart - $firstBlockStartTime;
            $duration            = $wallClockEnd - $wallClockStart;
            
            // Stitch audio together, then trim down to the time range given.
            file_put_contents($blockListFilePath, $blockList);
            if (exec("ffmpeg -y -loglevel error -hide_banner -f concat -safe 0 -i \"$blockListFilePath\" -c copy -ss $blockStartToRangeStart -t $duration \"$stitchedFilePath\"", $output) === false) {
                error_log("ffmpeg stitch failed: " . implode('\n', $output));
                unset($output);
                return array();
            }
            unlink($blockListFilePath);
            
            return $stitchedFileName;
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
     * @return string  The name of the trimmed file, which will be in the holding directory, or empty string on error.
     */
    public function trim(int $start, int $duration, string $fileName): string {
        $filePath         = dirname(__FILE__) . "/../stitched/" . $fileName;
        $explodedFileName = explode(".", $fileName);
        $id               = $explodedFileName[0];
        $audioExtension   = end($explodedFileName);
        $outputFileName   = $id . "." . $audioExtension;
        $outputFilePath   = $this->config["holdingDirectory"] . "/" . $outputFileName;
        $fadeDuration     = $this->config["extraction"]["fadeDuration"];
        $fadeOutAt        = $duration - $fadeDuration;
        
        if ($fadeDuration == 0) {
            if (exec("ffmpeg -y -hide_banner -loglevel error -ss \"$start\" -i \"$filePath\" -t \"$duration\" -c copy \"$outputFilePath\"", $output) === false) {
                error_log("ffmpeg stitch failed: " . implode('\n', $output));
                unset($output);
                return "";
            }
        } else {
            set_time_limit(120);
            if (exec("ffmpeg -y -hide_banner -loglevel error -ss \"$start\" -i \"$filePath\" -t \"$duration\" -af afade=in:0:d=$fadeDuration,afade=out:st=$fadeOutAt:d=$fadeDuration \"$outputFilePath\"", $output) === false) {
                error_log("ffmpeg stitch with fade failed: " . implode('\n', $output));
                unset($output);
                return "";
            }
        }
        
        return $outputFileName;
    }
}
