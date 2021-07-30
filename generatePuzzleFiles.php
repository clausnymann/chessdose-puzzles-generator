<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "lichess_puzzles";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$filePath = "C:\\/apps\\/puzzles\\/generated\\/puzzleThemeDefinitions.json";
// @unlink($filePath);
$result = mysqli_query($conn, "SELECT themeKey, theme, description FROM themes ORDER BY themeKey");
$themes = [];

foreach($result -> fetch_all(MYSQLI_ASSOC) as $theme){
    $themes[$theme['themeKey']] = [
        'theme' => $theme['theme'],
        'description' => $theme['description']
    ];
}

$fp = fopen('C:\\/apps\\/puzzles\\/generated\\/puzzleThemeDefinitions.js', 'w') or die("Unable to create ranges file!");
fwrite($fp, 'export const puzzleThemeDefinitions = '.json_encode($themes));
fclose($fp);

/*
CREATE TABLE `puzzles` (
  `id` int(11) NOT NULL,
  `PuzzleId` varchar(6) COLLATE latin1_general_ci NOT NULL,
  `FEN` varchar(100) COLLATE latin1_general_ci NOT NULL,
  `Moves` varchar(500) COLLATE latin1_general_ci NOT NULL,
  `Rating` int(4) NOT NULL,
  `RatingDeviation` int(4) NOT NULL,
  `Popularity` int(4) NOT NULL,
  `NbPlays` int(3) NOT NULL,
  `Themes` varchar(300) COLLATE latin1_general_ci NOT NULL,
  `GameUrl` varchar(100) COLLATE latin1_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

ALTER TABLE `puzzles`
  ADD PRIMARY KEY (`id`)

ALTER TABLE `puzzles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;


$filePath = "lichess_db_puzzle.csv";
//populate db
$sql = "
        LOAD DATA LOCAL INFILE '".$filePath."' 
        INTO TABLE puzzles
        COLUMNS 
            TERMINATED BY ','
        LINES
            TERMINATED BY '\n'
            (PuzzleId,FEN,Moves,Rating,RatingDeviation,Popularity,NbPlays,Themes,GameUrl)
        ";


$conn->query($sql);
*/
// filters
$MinNbPlays = 100;
$MinPopularity = 80; // 0-100

//export to csv files
$result = mysqli_query($conn, "SELECT MIN(Rating) AS minRating, MAX(Rating) AS maxRating FROM puzzles WHERE NbPlays >= ".$MinNbPlays." AND Popularity > ".$MinPopularity);
$range = mysqli_fetch_array($result);

$splits = 20;
$resultsPrSplit = 2000;
$increments = round(($range[1] - $range[0]) / $splits);
$fileSuffix = 1;

$files = glob("C:\\/apps\\/puzzles\\/generated\\/*"); // get all file names
foreach($files as $file){ // iterate files
    if(is_file($file)) {
        unlink($file); // delete file
    }
}
$totalPuzzles = 0;
$ranges = [];

for($i=$range[0]; $i < $range[1]-$increments; $i += $increments){
        $ratingMax = $i+($increments*2) > $range[1] ? $range[1] : $i+$increments;
        // $filePath = "C:\\/Users\\/Skoleskak-Claus\\/Desktop\\/puzzles\\/generated\\/puzzles".$fileSuffix.".csv";
        // Are there enough puzzles for the next rating span? if not included it in this one
        $result = mysqli_query($conn, "SELECT COUNT(*) FROM puzzles WHERE NbPlays > ".$MinNbPlays." AND Popularity >= ".$MinPopularity." AND Rating > ".$ratingMax);
        $countNext = mysqli_fetch_array($result)[0];
        if($countNext < $resultsPrSplit / 2){
            $ratingMax = $range[1];
            echo $countNext.' puzzles added last range results.<br>';
        }

        $filePath = "C:\\/apps\\/puzzles\\/generated\\/p".$fileSuffix.".csv";
        //@unlink($filePath);
        $sql = " 
        SELECT PuzzleId,FEN,Moves,Rating,RatingDeviation,Popularity,NbPlays,Themes  FROM puzzles WHERE NbPlays > ".$MinNbPlays." AND Popularity >= ".$MinPopularity." AND Rating BETWEEN ".$i." AND  ".$ratingMax." 
        ORDER BY Popularity, LENGTH(Moves), Rating LIMIT ".$resultsPrSplit." 
        INTO OUTFILE '".$filePath."' 
        FIELDS OPTIONALLY ENCLOSED BY '' 
        TERMINATED BY ',' 
        ESCAPED BY '' 
        LINES TERMINATED BY '\r\n'";
        $conn->query($sql);

        $filePath = "C:\\/apps\\/puzzles\\/generated\\/tp".$fileSuffix.".csv";
       // @unlink($filePath);
        $sql2 = " 
        SELECT d.themeKey, COUNT(p.id)
        FROM themes d 
        LEFT JOIN (SELECT * FROM puzzles WHERE NbPlays > ".$MinNbPlays." AND Popularity >= ".$MinPopularity." AND Rating BETWEEN ".$i." AND ".$ratingMax." ORDER BY Popularity, LENGTH(Moves), Rating LIMIT ".$resultsPrSplit.") p ON p.Themes LIKE CONCAT('%', d.themeKey, '%') 
        GROUP BY d.themeKey ORDER BY d.themeKey 
        INTO OUTFILE '".$filePath."' 
        FIELDS OPTIONALLY ENCLOSED BY ''
        TERMINATED BY ',' 
        ESCAPED BY '' 
        LINES TERMINATED BY '\r\n'";
        $conn->query($sql2);

        $result = mysqli_query($conn, "SELECT COUNT(*) FROM puzzles WHERE NbPlays > ".$MinNbPlays." AND Popularity >= ".$MinPopularity." AND Rating BETWEEN ".$i." AND  ".$ratingMax." LIMIT ".$resultsPrSplit);
        $count = mysqli_fetch_array($result)[0];
        echo ($count > $resultsPrSplit ? $resultsPrSplit : $count).' puzzles added from '.$count.' range '.$i.' - '.$ratingMax.'<br>';

        $ranges[] = [
            'min' => (int)$i,
            'max' => (int)$ratingMax,
            'count' => (int)($count > $resultsPrSplit ? $resultsPrSplit : $count),
        ];

        $totalPuzzles+=$count;
        if($countNext < $resultsPrSplit / 2){
            break;
        }
       $fileSuffix++;
}


$fp = fopen('C:\\/apps\\/puzzles\\/generated\\/puzzleRanges.js', 'w') or die("Unable to create ranges file!");
fwrite($fp, 'export const puzzleRanges = '.json_encode($ranges));
fclose($fp);

$conn->close();
echo $totalPuzzles.' total puzzless added range files.<br>';
