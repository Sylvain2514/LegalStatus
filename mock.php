<?php

$files_arr = ["US7745652_B2","US8518158_B2","US20140225030_A1","US20150266997_A1","US10421898_B2","US20200141638_A1"];

$file_name = $files_arr[5];

$db_host = 'localhost';
$db_user = 'root';
$db_password = 'root';
$db_db = 'legalstatus';

$mysqli = @new mysqli(
  $db_host,
  $db_user,
  $db_password,
  $db_db
);

if ($mysqli->connect_error) {
  echo 'Errno: '.$mysqli->connect_errno;
  echo '<br>';
  echo 'Error: '.$mysqli->connect_error;
  exit();
}

$string = file_get_contents($file_name . ".json");
$json = json_decode($string, true);

$ACT = $json["documents"][0]["ACT"];

$ParseACT = ACT_parse($ACT);


$result = mysqli_query($mysqli,"SELECT * FROM Node ORDER BY id ASC");

while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
   $Nodes_arr[$row["id"]] = Array(
     "id"=>$row["id"],
     "where_are_we" => 0,
     "name"=> $row["name"],
     "x"=> $row["x"],
     "y"=> $row["y"],
     "width"=> $row["width"],
     "height"=> $row["height"],
     "opacity"=> "0.1",
     "linestarts" => $row["linestarts"],
     "lineends" => $row["lineends"],
     "borderstyle"=> (($row["dashed"]) == 1 ? "5,4" : "None")
   );
}

$result = mysqli_query($mysqli,"SELECT * FROM Code WHERE country='US'");

while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
   $Codes_arr[$row["code"]] = Array(
     "country"=> $row["country"],
     "code"=> $row["code"],
     "name"=> $row["name"],
     "simple"=> $row["simple"],
     "country_specific"=> $row["country_specific"]
   );
}

$Legals_arr = Array();
$Links_arr = Array(); // $Links_arr 1=>object
$NameLinks_arr = Array(); //$NameLinks_arr[source][target] = 1 to keep track of existing links

foreach($ParseACT as $key=>$value){
  if(array_key_exists($value["CO"], $Codes_arr)){
    $code_var = $value["CO"];
    $simple_id = $Codes_arr[$code_var]["simple"];

    $country_specific_id = $Codes_arr[$code_var]["country_specific"];


    if(!isset($where_are_we) && $Nodes_arr[$country_specific_id]["linestarts"]==1){
      $where_are_we = $country_specific_id;
    }

    if(isset($where_are_we) && $Nodes_arr[$country_specific_id]["lineends"]==1){
       if($country_specific_id != $where_are_we){
        if(!isset($NameLinks_arr[$where_are_we][$country_specific_id])){
          $NameLinks_arr[$where_are_we][$country_specific_id]=1;
          $Links_arr[] = Array(
            "source"=> $where_are_we,
            "target"=> $country_specific_id,
            "weight"=> 1,
            "name"=> $value["AD"]
          );
        }
        else {
          $Links_arr[] = Array(
            "source"=> $where_are_we,
            "target"=> $country_specific_id,
            "weight"=> 1,
            "name"=> ""
          );
        }

        foreach($Nodes_arr as $k=>$v){
          $Nodes_arr[$k]["where_are_we"] = 0;
        }

        if($Nodes_arr[$country_specific_id]["opacity"] == 1){
          $Nodes_arr[] = $Nodes_arr[$country_specific_id];
          $last_key = array_key_last($Nodes_arr);
          $Nodes_arr[$last_key]["x"] -= 5;
          $Nodes_arr[$last_key]["y"] += 5;
          $Nodes_arr[$last_key]["opacity"] = 1;
          $Nodes_arr[$last_key]["id"] =  $Nodes_arr[$last_key]["id"]."_1";
          $Nodes_arr[$country_specific_id]["visibility"]="hidden";
          $Nodes_arr[$last_key]["where_are_we"] = 1;
        }
        else {
          $Nodes_arr[$country_specific_id]["where_are_we"] = 1;
        }

        $where_are_we = $country_specific_id;

      }


    }

    // set opacity to 1 for all nodes that are within the ACT
    $Nodes_arr[$simple_id]["opacity"] = 1;
    $Nodes_arr[$country_specific_id]["opacity"] = 1;

    $Legals_arr[]=Array(
      "date"=> $value["AD"],
      "name"=> $value["value"],
      "code"=> $code_var,
      "simple"=> $Codes_arr[$code_var]["simple"],
      "country_specific"=> $Codes_arr[$code_var]["country_specific"]
    );
  }
}


$final_json_arr = Array(
  "nodes" => array_values($Nodes_arr),
  "links" => array_values($Links_arr),
  "legals" => array_values($Legals_arr),
);


$mysqli->close();



$final_json = json_encode($final_json_arr);
echo($final_json);

function ACT_parse($ACT){
  $ACT_arr = explode("AD=", $ACT);
  foreach($ACT_arr as $key=>$value){
    if((substr($value, 0, 2) == "19") || (substr($value, 0, 2) == "20")){
      $CO_var = "";
      $Reste_var = ltrim(substr($value, 10));

      $COPos_var = strpos($Reste_var,"CO=");

      if($COPos_var !== false){
        $Reste_var = substr($Reste_var, $COPos_var);
        $EndCO_var = strpos($Reste_var, " ");

        if ($EndCO_var !== false){
          $CO_var = substr($Reste_var, $COPos_var+3, $EndCO_var-3);
          $Reste_var=substr($Reste_var, $EndCO_var);
        }
      }
      else {
        echo "no";
      }

      $BRPos_var = strpos($Reste_var, "<br/>");
      if ($BRPos_var !== false){
        $Reste_var = substr($Reste_var, $BRPos_var + 5);
      }

      $ACT_id = array(
        "AD"=>substr($value, 0, 10),
        "CO"=>$CO_var,
        "value"=>$Reste_var
      );
      $ACT_arr[$key] = $ACT_id;
    }
    else{
      array_splice($ACT_arr,$key);
    }
  }

  function sortByDate($a, $b) {
      if ($a['AD'] > $b['AD']){
        return 1;
      }
      elseif($a['AD'] < $b['AD']){
        return -1;
      }
      return 0;
  }

  usort($ACT_arr, 'sortByDate');


  return($ACT_arr);
}


 ?>
