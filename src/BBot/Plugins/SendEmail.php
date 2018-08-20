<?php

namespace BBot\Plugins;

use Monolog\Registry as Log;

/**
 * SendEmail plugin - defined .flow function sendEmail to send emails
 */
class SendEmail
{

    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }

    public static function init($bbot)
    {
        $bbot->registerDotFlowFunction('sendEmail', [__CLASS__, 'sendEmail']);
    }

    public function sendEmail($node)
    {
        
        if ($node->info->formId) {//if there is a form defined, send the form too
            $form = $this->bbot->userData->get("formVars.{$node->info->formId}");
            //build form
            $formBody = "";
            foreach($form as $f) {
                $formBody .= "{$f['question'][0]}: {$f['answer']}\n";
            }    
        }
        //takes email data from node
        $message = $this->bbot->interpolate($node->info->body); 
        $subject = $this->bbot->interpolate($node->info->subject);
        $to = $node->info->recipient;
        $from = $this->bbot->dotBot->bot->senderEmailAddress;
        
        $bodyMessage = $message . "\n\n" . $formBody;
        
        $headers = "From:" . $from;
        mail($to, $subject, $bodyMessage, $headers);
        
        Log::bbotcore()->debug('Sending email to ' . $to . ' with form:' . json_encode($form));
        

    }

}
