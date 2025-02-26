<?php
namespace Stanford\GoProd;
 use ExternalModules\AbstractExternalModule;
include_once "classes/messages.php";

class GoProd extends AbstractExternalModule
{
    const PATH_TO_RULES= 'rules/';
    function hook_every_page_top($project_id)
	{

       // $this->log("Here", func_get_args());
	    $goprod_workflow=$this->getProjectSetting("gopprod-workflow");

        if(PAGE == 'ProjectSetup/index.php' and isset($project_id) and $goprod_workflow==1){
            ?>
                <script>
                    $(document).ready(function() {
                        //find and hide the current go to prod button
                        var MoveProd=  $( "button[onclick='btnMoveToProd()']" );
                        MoveProd.hide();
                        //add the new go to pro button
                        gopro_button= '<button id="go_prod_plugin" class="btn btn-defaultrc btn-xs fs13">'+ '<?php echo lang('GO_PROD');?>' +'</button>';
                        MoveProd.after(gopro_button);
                        document.getElementById("go_prod_plugin").onclick = function () {
                            production =  <?php  echo json_encode($this->getUrl("index.php"))?>;
                            location.href = production;
                        };
                        ready_to_prod = <?php echo json_encode($_GET["to_prod_plugin"])?>;
                        if (ready_to_prod === '1'){
                            MoveProd.click();
                            $( 'div[aria-describedby="certify_prod"]' ).hide();
                            // setTimeout(function(){
                            // $('.ui-dialog-buttonpane button').click();
                            // },500);
                        }
                        if (ready_to_prod === '3'){
                            //in case of IRB ,  PI  or purpose errors found
                             displayEditProjPopup();
                        }
                    });
                </script>
            <?php
        }
	}


    /**********************************************************************************************************
    Read all file names on the /rules folder and creates an array with all the names removing the .php part
    ..... -maybe PrintRulesNames is not the best name-
     **********************************************************************************************************/
    function GetListOfActiveRules(){
        $RuleNames = array();
        $url= __DIR__.'/'.$this::PATH_TO_RULES;
        foreach (scandir($url) as $filename)
        {
            if(ctype_upper(substr($filename, 0, 1)) ){
                $filename=preg_replace('/\.[^.]+$/','',$filename);
                array_push($RuleNames, $filename);
            }
        }

        //TODO: filter skipped rules::
        //load skipped rules from logg
        // $res= new ReadWriteLogging($_GET['pid']);
        //  $RuleNames=$res->GetActiveRules($RuleNames);


        return $RuleNames;
    }

    /*********************************************************************************
     * Call and execute a given rule --- returns (print) the json_encode  for the view
     ********************************************************************************* */
    function CallRule($RuneName){

        //path to the rule folder

        $phat_to_rule=$this::PATH_TO_RULES.$RuneName.'.php';

        //Dynamic include the path of the rule in order to be called -- exit if fails
        if(!@include_once($phat_to_rule)) {
            error_log("Failed to include:: $phat_to_rule");
            exit();
        }

        //Check if the path and function exist. if not then exit.
        if (!(function_exists(__NAMESPACE__."\\".$RuneName))) {
            error_log( "Problem loading  $RuneName does not exist");
            exit();
        }

        //Call the Rule the function  call_user_func helps to call functions directly from a path  and save the result in $ResultRulesArray
        $ResultRulesArray = call_user_func(__NAMESPACE__."\\".$RuneName);
        //read the results sof the function: if the rule returns true, then extract the Html to present on the views - if not return false
        if(!empty($ResultRulesArray['results'])){
            //if found problems
            $a=$this::PrintAHref("views/results.php");// results.php is the DATA TABLE that shows the list of issues
            $span=$this::PrintLevelOfRisk($ResultRulesArray['risk']);
            $print=$this::PrintTr($ResultRulesArray['title'],$ResultRulesArray['body'],$span,$a,$RuneName);
            $ResultRulesArray=$ResultRulesArray['results'];
            $result[$RuneName]= array("Results"=>$ResultRulesArray,"Html"=>$print);
            $res = json_encode($result);
            echo $res;
        }
        else {
            //if no problem found
            error_log(">>>>>Call Rule returns false with rule= $RuneName <<<<<");
            $res=json_encode(false);
            echo $res;
        }
    }


    /***************************************************************************************
     * The functions below are in chagrge of printing, and adding the HTML to the rules
     *
     * (probably in the future this group of functions will be moved to an external class)
     *
     ************************************************************************************** */

    function PrintTr($title_text,$body_text,$span_label,$a_tag,$rulename){
        $value=
            '<tr class="gp-tr" id="'.$rulename.'" style="display: none">
            <td class="gp-info-content">
                <div class="gp-title-content gp-text-color">
                      <b>'.$title_text.' </b> 
                     <span class="title-text-plus" style="color: #7ec6d7"><small>(hide)</small></span> 
                </div>
                    
                <div class="gp-body-content overflow  gp-text-color" >
                    <p class="list-group-item-text" >
                        ' .$body_text.' 
                    </p>
                </div>
            </td>
            <td class="center " width="100">               
                    '.$span_label.' 
            </td>
            <td class="gp-actions center" width="100">               
                 <div class="gp-actions-link" style="display: none">'.$a_tag.'</div>    
            </td>
        </tr>';
        // $value=htmlentities(stripslashes(utf8_encode($value)), ENT_QUOTES);
        return $value;
    }
//Print the level of risk or the rule
     function PrintLevelOfRisk($type){
         $size='fa-2x';
         switch ($type) {

             case "warning":
                 $risk_title=lang('WARNING');
                 $risk_color='text-warning';
                 //$risk_icon='glyphicon glyphicon-exclamation-sign';
                 $risk_icon='fa fa-exclamation-triangle';
                 break;
             case "danger":
                 $risk_title=lang('DANGER');
                 $risk_color='text-danger';
                 $risk_icon='fa fa-fire';
                 break;
             case "success":
                 $risk_title=lang('SUCCESS');
                 $risk_color='text-success';
                 $risk_icon='fa fa-thumbs-up';
                 $size='fa-5x';
                 break;
             case "info":
                 $risk_title=lang('INFO');
                 $risk_color='text-info';
                 $risk_icon='fa fa-info-circle';
                 break;
             default: //just in case
                 $risk_title=lang('INFO');
                 $risk_color='text-info';
                 $risk_icon='fa fa-info-circle';
         }
         return '<abbr title='.$risk_title.'><span class="'.$size.' '.$risk_icon.' '.$risk_color.'" aria-hidden="true"></span></abbr>';
     }
     function PrintAHref($link_to_view){
         global $new_base_url;
         $link= $new_base_url . "i=" . rawurlencode($link_to_view);
         return '<a href="#ResultsModal" role="button"   class="btn  btn-default btn-lg review_btn" data-toggle="modal" data-link='.$link.' data-is-loaded="false">'.lang('VIEW').'</a>';
     }





}
