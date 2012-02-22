<?php
// This script attempts to close all open attempts for a specific quiz
require_once($CFG->libdir.'/tablelib.php');

class quiz_report extends quiz_default_report {

    function display($quiz, $cm, $course) {
        global $CFG;

        // Print header
        $this->print_header_and_tabs($cm, $course, $quiz, $reportmode="closeall");

        // Check permissions
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        if (!has_capability('mod/quiz:grade', $context)) {
            notify(get_string('regradenotallowed', 'quiz'));
            return true;
        }
	
	// get all unfinished attempt for the quiz
	$attempts= get_records_select('quiz_attempts',
	"quiz = '{$quiz->id}' AND  timefinish=0 AND preview = 0",
            'attempt ASC');
	
	 if ($attempts) {
		 // that means that someone has an unfinished attempt so try to close it...
		 foreach($attempts as $attempt) {
			 // Set the attempt to be finished with the timestamp of now
			 $attempt->timefinish = time();

			 // load all the questions
			 $closequestionlist = quiz_questions_in_quiz($attempt->layout);
			 $sql = "SELECT q.*, i.grade AS maxgrade, i.id AS instance".
			 "  FROM {$CFG->prefix}question q,".
			 "       {$CFG->prefix}quiz_question_instances i".
			 " WHERE i.quiz = '$quiz->id' AND q.id = i.question".
			 "   AND q.id IN ($closequestionlist)";
			 if (!$closequestions = get_records_sql($sql)) {
				 error('Questions missing');
			 }

			 // Load the question type specific information
			 if (!get_question_options($closequestions)) {
				 error('Could not load question options');
			 }

			 // Restore the question sessions
			 if (!$closestates = get_question_states($closequestions, $quiz, $attempt)) {
				 error('Could not restore question sessions');
			 }
			 // try to close each question...
			 $success = true;
			 foreach($closequestions as $key => $question) {
				 $action->event = QUESTION_EVENTCLOSE;
				 $action->responses = $closestates[$key]->responses;
				 $action->timestamp = $closestates[$key]->timestamp;
				 
				 if (question_process_responses($question, $closestates[$key], $action, $quiz, $attempt)) {
					//hack to force state change?
					//$closestates[$key]->changed=true;
					 save_question_session($question, $closestates[$key]);
				 } else {
					 $success = false;
				 }
			 }
			 
			 //some sort of error report if unsuccessful?
			 if (!$success) {
				 //$pagebit = '';
				 //if ($page) {
				//	 $pagebit = '&amp;page=' . $page;
				// }
				 link_to_popup_window ('/mod/quiz/reviewquestion.php?attempt='.$attempt->id,
                     'reviewquestion', ' #'.$attempt->id, 450, 550, get_string('reviewresponse', 'quiz'));
				 print_error('errorprocessingresponses', 'question',
					 $CFG->wwwroot . '/mod/quiz/attempt.php?q=' . $quiz->id . $pagebit);
			 } else {
				 echo ' #'.$attempt->id.' was closed';
				 // ok the event is logged as closed, now we need to update the timefinish field on the attempt
				 update_record('quiz_attempts', $attempt);
				 // and finally save the best grade for the quiz, user combo
				 quiz_save_best_grade($quiz, $attempt->userid);
			 }
			  echo '<br />';
			 // and when all is said and done log it....
			 add_to_log($course->id, 'quiz', 'close attempt',
                           "review.php?attempt=$attempt->id",
                           "$quiz->id", $cm->id);
		 }
	 }
	 
	 return true;
    }
}

?>
