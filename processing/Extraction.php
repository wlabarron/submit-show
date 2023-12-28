<?php


namespace submitShow;

/**
 * Tools to extract a show from linear output recordings by stitching together chunked recording files
 * then trimming the file down to the show's start and end.
 */
class Extraction {
    // TODO Move this into config file
    private static $recordingUnit = 3600;
    // with trailing slash
    private static $recordingDirectory = "";
    private array $config;
    private string $id;
    
    public function __construct($id = null) {
        $this->config = require "config.php";
        if (!is_null($id)) {
            $this->id = $id;
        } else {
            $this->id = uniqid();
        }
    }
    
    /**
     * Finds stream recording block file names which span the given timeframe.
     * If you asked for 1:45pm-3:45pm and each block file was an hour long and starting on the hour, you'd be given 1pm, 2pm 
     * and 3pm's files.
     * 
     * @param string $startTime Wall clock time to start getting blocks from, parseable by strtotime.
     * @param string $endTime Wall clock time to of the final block, parseable by strtotime.
     * @return array  Array of file names for the recording blocks which cover this time.
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
                    array_push($relevantFiles, Extraction::$recordingDirectory . $file);
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
            
            $blockListFilePath = $this->config["tempDirectory"] . "/" . $this->id . ".list";
            $stitchedFilePath  = $this->config["tempDirectory"] . "/" . $this->id . "." . $audioExtension;
            
            file_put_contents($blockListFilePath, $blockList);
            set_time_limit(120);
            echo(shell_exec("ffmpeg -f concat -safe 0 -i \"$blockListFilePath\" -c copy \"$stitchedFilePath\""));
            unlink($blockListFilePath);
            
            return $stitchedFilePath;
        } else {
            return "";
        }
    }
}

$extraction = new Extraction();
echo($extraction->stitch("20231227-054500", "20231227-071500"));