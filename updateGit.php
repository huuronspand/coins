<?php
ignore_user_abort(true);

function syscall ($cmd, $cwd) {
    $descriptorspec = array(
        1 => array('pipe', 'w'), // stdout is a pipe that the child will write to
        2 => array('pipe', 'w') // stderr
    );
    $resource = proc_open($cmd, $descriptorspec, $pipes, $cwd);
    if (is_resource($resource)) {
        $output = stream_get_contents($pipes[2]);
        $output .= PHP_EOL;
        $output .= stream_get_contents($pipes[1]);
        $output .= PHP_EOL;
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($resource);
        return $output;
    }
}

function git_current_branch ($cwd) {
    $result = syscall('git branch', $cwd);
    if (preg_match('/\\* (.*)/', $result, $matches)) {
        return $matches[1];
    }
}

function mailTest($subject,$response){
    //TODO: email who did the commit would be nice
    $to      = 'huuronspand@gmail.com';
    $headers[] = 'From: marcovdk@gmail.com';
    $headers[] = 'Reply-To: marcovdk@gmail.com';
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    $headers[] = 'Content-type: text/html; charset=iso-8859-1';

    $message = sprintf("<html><head></head><body>%s</body></html>", $response);

    mail($to, $subject, $message, implode("\r\n", $headers));
}

// GitHub will hit us with POST (http://help.github.com/post-receive-hooks/)
include "/var/www/coins/gitConfig.php";

$headers = apache_request_headers();

$payload = file_get_contents( 'php://input' );
$signature = str_replace("sha1=","",$headers['X-Hub-Signature']);
$compare = hash_hmac("sha1", $payload, $config->secret);

if($signature == $compare) {
    $payload = json_decode($payload);

    // which branch was committed?
    $branch = str_replace("refs/heads/", "", $payload->ref);

    // If your website directories have the same name as your repository this would work.
    $repository = $payload->repository->name;
    $cwd = $config->path;

    // only pull if we are on the same branch
    if ($branch == git_current_branch($cwd)) {

        // pull from $branch
        $cmd = sprintf('git pull origin %s', $branch);
        $result = syscall($cmd, $cwd);

        $output = '';

        // append commits
        foreach ($payload->commits as $commit) {
            $output .= $commit->author->name.' a.k.a. '.$commit->author->username;
            $output .= PHP_EOL;
            foreach (array('added', 'modified', 'removed') as $action) {
                if (count($commit->{$action})) {
                    $output .= sprintf('%s: %s; ', $action, implode(',', $commit->{$action}));
                }
            }
            $output .= PHP_EOL;
            $output .= sprintf('because: %s', $commit->message);
            $output .= PHP_EOL;
            $output .= $commit->url;
            $output .= PHP_EOL;
        }

        // append git result
        $output .= PHP_EOL;
        $output .= $result;

        // send us the output
        print_r('GitHub hook `'.$cmd.'` result');
        print_r($output);

        //globalVersionNumber Auto upgrade
        //$FileBlank =  "../V001/cache/globalVersionNumber.txt";
        //touch($FileBlank);
        //$ourFileHandle = fopen($FileBlank, 'r') or die("can't open file");
        //$globalVersionNumber = stream_get_contents($ourFileHandle);
        //fclose($ourFileHandle);

        //if(!is_numeric($globalVersionNumber))
        //    $globalVersionNumber = 800;

        //$globalVersionNumber++;
        //touch($FileBlank);
        //$ourFileHandle = fopen($FileBlank, 'w') or die("can't open file");
        //fwrite($ourFileHandle, $globalVersionNumber);
        //fclose($ourFileHandle);



    } else {
        echo("wrong branch");
    }
} else {
    echo "no auth";
}

?>
