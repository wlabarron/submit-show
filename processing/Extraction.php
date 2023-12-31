<?php

namespace submitShow;

require __DIR__ . "/Storage.php";

use Storage;

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
        // TODO Sort
        // TODO Missing blocks/time
        return $relevantFiles;
    }

    /**
     * Stitches together recording blocks to give one audio file over the specified times.
     *
     * @param string $startTime Wall clock time to start stitching from, parseable by strtotime.
     * @param string $endTime Wall clock time to end stitching at, parseable by strtotime.
     * @param string $destination Where to write the stitched file to, including file name, but excluding extension which will be appended
     *                            the block files.
     * @param bool $fade Whether to put a fade on each end of the file or not.
     * @return string File path written to, or empty string on failure.
     */
    public function stitch(string $startTime, string $endTime, string $destination, bool $fade): string {
        $blocks = $this->getBlocks($startTime, $endTime);
        
        $wallClockStart = strtotime($startTime);
        $wallClockEnd   = strtotime($endTime);
        
        if ($fade) {
            $fadeDuration     = $this->config["extraction"]["fadeDuration"];
            $fadeOutAt        = $wallClockEnd - $wallClockStart - $fadeDuration;
            $effect = "-af afade=in:0:d=$fadeDuration,afade=out:st=$fadeOutAt:d=$fadeDuration";
        } else {
            $effect = "";
        }
        
        if (sizeof($blocks) > 0) {
            $blockList = ""; 
            foreach ($blocks as &$block) {
                $blockList .= "file '" . $block . "'\n";
            }
            
            $explodedFirstFileName = explode(".", $blocks[0]);
            $audioExtension        = end($explodedFirstFileName);
            $destination = $destination . "." . $audioExtension;
            Storage::createParentDirectories($destination);
            
            $blockListFilePath = $this->config["tempDirectory"] . "/" . uniqid() . ".list";
            
            // The blocks returned will probably span longer than the time requested. Calculate the difference between the start of the
            // first block and the start of the time range we want, then the duration of the time range we want.
            $firstBlockPathSplit = explode("/", $blocks[0]);
            $firstBlockFileName  = end($firstBlockPathSplit);
            $firstBlockStartTime = strtotime($firstBlockFileName);
            $blockStartToRangeStart = $wallClockStart - $firstBlockStartTime;
            $duration            = $wallClockEnd - $wallClockStart;
            
            // Stitch audio together, then trim down to the time range given.
            file_put_contents($blockListFilePath, $blockList);
            if (exec("ffmpeg -y -loglevel error -hide_banner -f concat -safe 0 -i \"$blockListFilePath\" -ss $blockStartToRangeStart -t $duration $effect \"$destination\"", $output) === false) {
                error_log("ffmpeg stitch failed: " . implode('\n', $output));
                unset($output);
                // TODO Exception
                return array();
            }
            unlink($blockListFilePath);
            
            return $destination;
        } else {
            error_log("No blocks");
            // TODO Exceptions
            return "";
        }
    }
}
