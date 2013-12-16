<?php
/**
* 2010.11.02.
*
* The purpose of the cl tag is to provide an easier syntax than HTML for
* creating lists with such things as: multiple paragraphs per list item;
* list items containing pre blocks; list items containing tables; list items
* containing tables containing lists; and so on.
*/

/**
* Check if we are being called directly
*/
if ( !defined( 'MEDIAWIKI' ) ) {
        die( 'This file is an extension to MediaWiki and thus not a valid entry point.' );
}

/**
* Add the hook function call to an array defined earlier in the wiki code
* execution.
*/
$wgExtensionFunctions[] = "efCl";

/**
* Add extension credits to the Special:Version
*/
$wgExtensionCredits['parserhook'][] = array(
        'path' => __FILE__,
        'name' => 'ComplexList',
        'author' => 'Emufarmers',
        'description' => 'Adds <nowiki><cl></nowiki> tags for complex lists',
        'descriptionmsg' => 'Adds <nowiki><cl></nowiki> tags for complex lists',
        'url' => 'https://www.mediawiki.org/wiki/Extension:ComplexList'
);

/**
* This is the hook function. It adds the "cl" tag to the wiki parser and
* tells it which callback function (namely, "efClRender") to use for the
* tag.
*/
function efCl() {
    global $wgParser;
    # register the extension with the WikiText parser
   $wgParser->setHook( "cl", "efClRender" );
}

/**
* The callback function.
*
*/
function efClRender( $input, $attribs ) {

    global $wgParser;

    // copied this line from another extension
    // seems to be necessary.
    $content = StringUtils::delimiterReplace( '<nowiki>', '</nowiki>', '$1', $input, 'i' );

    $attribs = Sanitizer::validateTagAttributes( $attribs, 'pre' );

    // Set some flags with initial values;
    $indent_count = 0;
    $indent_count_preceding = 0;
    $prefix = '';
    $new_content = '';
    $name_of_the_first_opening_tag = '';
    $count_of_instances_of_elements_matching_first_opening_tag = 0;
    $skip_this_line = 0;  // ie, false
    $skip_next_line = 0;  // ie, false
   
    // A trip-wire to get the WikiText parser to put each new line into an
    // HTML p element.  ...which seems to work only if the line does not
    // contain an HTML block-level opening tag (eg <blockquote> or <pre>).
    $paragraph_trip_wire = '<p style="display:none;"></p>';

    // Create an array of the lines of text between the <cl> opening tag and the </cl> closing tag.
    $cl_lines = explode("\n", $content);
   
    // Define a list of (HTML) elements an opening tag of which in one line
    // should prevent subsequent lines from being prefixed with list tags
    // (<ol>, <ul>, <li>) until a line is reached with a corresponding closing
    // tag.

    // Still have to add this to the regex: <!-- ... -->
       
    // Because we want to be able to allow (ie, not interpret) any old
    // string between angle brackets, <like this>, we should instead
    // match only the mediawiki-allowed HTML tags, and match them only
    // if we are not in a nowiki element - as is the behaviour of mediawiki.
   
    // Should it be all mediawiki-acceptable HTML elements . . .
    /*
    $array_of_tags_to_match = array(
            'abbr',         'h1',           's',
            'b',            'h2',           'small',
            'big',          'h3',           'span',
            'blockquote',   'h4',           'strike',
            'br',           'h5',           'strong',
            'caption',      'h6',           'sub',
            'center',       'hr',           'sup',
            'cite',         'i',            'table',
            'code',         'ins',          'td',
            'dd',           'li',           'th',
            'del',          'ol',           'tr',
            'div',          'p',            'tt',
            'dl',           'pre',          'u',
            'dt',           'rb',           'ul',
            'em',           'rp',           'var',
            'font',         'rt',           'ruby'      
            );
    */
    // . . . or just pre?
    $array_of_tags_to_match = array('pre', 'blockquote');
    $array_of_all_tags_to_match = array_merge($array_of_tags_to_match, $wgParser->getTags());
       
    // Tried other ways (eg, unset() and
    // $my_new_array = array_diff_assoc($my_array,array(”key1″=> $my_array["key1"]));
    // ) to get rid of "cl" tag introduced by $wgParser->getTags() but had
    // no success until I tried this:
    foreach($array_of_all_tags_to_match as $this_tag) {
        if($this_tag != 'cl') $tag_list_without_cl[] = $this_tag;
    }
    $tag_list_without_duplicates = array_unique($tag_list_without_cl); // Just in case there are duplicates.
    $tags_to_match = implode("|", $tag_list_without_duplicates);

	$list_stack = array();
    foreach($cl_lines as $cl_line) {

        // Skip blank lines.
        if(strlen(trim($cl_line)) == 0) {
            $new_content .= $prefix . trim($cl_line) . "\n";
            continue;
        }

        // Assign the value of the skip_next_line flag, set in the preceding
        // iteration of this foreach loop, this the skip_this_line flag.
        $skip_this_line = $skip_next_line;
       
        // Count the number of spaces by which this line is indented (indicating
        // to which one of possibly many nested lists it belongs.
        $cl_line_split = preg_split('/^[ ]+/', $cl_line, -1, PREG_SPLIT_OFFSET_CAPTURE);
        // $cl_line_split is an array of two-element arrays: split out chunk of
        // text is element [0], position of chunk is element [1].  This position
        // of the second chunk (the text after the spaces indent) is number of
        // spaces and, therefore, the number that we will call the indent_count.
        $indent_count = $cl_line_split[count($cl_line_split)-1][1];
       
        // Trim the indent from the line of content.
        $new_line = $cl_line_split[count($cl_line_split)-1][0];
       
        // Find out if this line opens an HTML element and doesn't close it.  
        // In which case, add any necessary prefix to this line, but skip (the
        // prefix-determining and prefix-adding process for) every following
        // this line until the first line after the line in which the element is
        // closed by a corresponding closing tag.
        if($name_of_the_first_opening_tag == '') {
            $blah = preg_match('/<('.$tags_to_match.')( +|>)/i', $new_line, $matches_first, PREG_OFFSET_CAPTURE);
	        if (isset($matches_first[1][0])) {
		        $name_of_the_first_opening_tag = $matches_first[1][0];
	        }
        }
       
        // Add one to the count of the tag-name for each matching opening
        // tag-found.
        // Why? Because if we have, for example, an X within an X, we then
        // will want to know that there are two Xs, not just one, that
        // need to be closed.
        $unneeded_value_01 = preg_match_all('/<('.$name_of_the_first_opening_tag.')[ *|>]/i', $new_line, $matches_of_opening_tag, PREG_OFFSET_CAPTURE);
        $count_of_instances_of_elements_matching_first_opening_tag += count($matches_of_opening_tag[1]);  // 1 is for match in the *1st* pair of brackets

        // Subtract one from the count of the tag-name for each matching
        // closing tag found.
        $unneeded_value_02 = preg_match_all('/<\/('.$name_of_the_first_opening_tag.')>/i', $new_line, $matches_of_closing_tag, PREG_OFFSET_CAPTURE);
        $count_of_instances_of_elements_matching_first_opening_tag -= count($matches_of_closing_tag[1]);  // 1 is for match in the *1st* pair of brackets
       
        // If this line leaves us within an element (because there was one or
        // more opening tag(s) with no accompanying closing tag(s)), then
        // we'll add a prefix to this line, but not the next (in order to not
        // start adding opening and closing list tags in the middle of the
        // still-unclosed user-entered element).
        if($count_of_instances_of_elements_matching_first_opening_tag > 0) {
            // Raise skip-next-line flag.
            $skip_next_line = 1;  // true
        }
        else {
            $skip_next_line = 0;  // false
            $name_of_the_first_opening_tag = '';
        }
       
        if($skip_this_line == 1) {
            // Leave this line as-is because we have found that it is in the
            // middle of a still-unclosed user-entered element.
            $new_line = $cl_line;  
        }
        else {
            // Determine and add a prefix for this line . . .

            // If indent count of this line is greater than that of the previous
            // line, and if the first non-white-space character is one of the
            // recognised new-line-item character-sets ("*", "1.", "a.",
            // "A.", "i.", "I."), then we start a new list.
            if($indent_count > $indent_count_preceding || count($list_stack) == 0) {
                if(
                        substr($new_line, 0, 2) == '* '  ||
                        substr($new_line, 0, 3) == '1. ' ||
                        substr($new_line, 0, 3) == 'a. ' ||
                        substr($new_line, 0, 3) == 'A. ' ||
                        substr($new_line, 0, 3) == 'i. ' ||
                        substr($new_line, 0, 3) == 'I. '
                        ) {
                    if(substr($new_line, 0, 2) == '* ') {
                        $list_name = 'ul';
                        $list_item_token_length = 2;
                        $list_style = '';
                    }
                    elseif(
                            substr($new_line, 0, 3) == '1. ' ||
                            substr($new_line, 0, 3) == 'a. ' ||
                            substr($new_line, 0, 3) == 'A. ' ||
                            substr($new_line, 0, 3) == 'i. ' ||
                            substr($new_line, 0, 3) == 'I. '
                            ) {
                        $list_name = 'ol';
                        $list_item_token_length = 3;
                        if(substr($new_line, 0, 3) == '1. ') $list_style = ' style="list-style-type: decimal;"';
                        if(substr($new_line, 0, 3) == 'a. ') $list_style = ' style="list-style-type: lower-latin;"';
                        if(substr($new_line, 0, 3) == 'A. ') $list_style = ' style="list-style-type: upper-latin;"';
                        if(substr($new_line, 0, 3) == 'i. ') $list_style = ' style="list-style-type: lower-roman;"';
                        if(substr($new_line, 0, 3) == 'I. ') $list_style = ' style="list-style-type: upper-roman;"';
                    }
                    $list_stack[] = array($list_name, $indent_count); // Add this new list to the stack of lists
                    $new_line = substr($new_line, $list_item_token_length);  // Trim the new-list-item token from the line of content.
                    $prefix = '<'.$list_name.$list_style.'><li>'.$paragraph_trip_wire."\n";
                    $indent_count_preceding = $indent_count;
                }
                else {
                    // If this line is indented, but not prefixed with a
                    // list-item token, put the additional indent back and move on.
                    $new_line = substr($cl_line, $indent_count_preceding);
                }
            }
            elseif($indent_count == $indent_count_preceding && substr($new_line, 0, 2) == '* ' && count($list_stack) > 0) {
                $new_line = substr($new_line, 2);  // Trim the new-line-item token from the line of content.
                $prefix .= '</li><li>'.$paragraph_trip_wire."\n";
                $indent_count_preceding = $indent_count;
            }
            elseif($indent_count < $indent_count_preceding && count($list_stack) > 0) {

                // If the indent of this line less than the indent of this
                // (nested) list, close the list.  Repeat for all lists until
                // we find a list whose indent matches this line's indent.
                $list_at_the_top_of_the_stack = end($list_stack);
                $indent_of_list_at_the_top_of_stack = $list_at_the_top_of_the_stack[1];
                while($indent_count < $indent_of_list_at_the_top_of_stack) {
                    $list_popped_off_the_top_of_the_stack = array_pop($list_stack);
                    $list_at_the_top_of_the_stack = end($list_stack);
                    $indent_of_list_at_the_top_of_stack = $list_at_the_top_of_the_stack[1]; // Re-set for next while test
                    $prefix .= '</li></'.$list_popped_off_the_top_of_the_stack[0].'>';
                }

                if(substr($new_line, 0, 2) == '* ' && count($list_stack) > 0) {
                    $new_line = substr($new_line, 2);
                    $prefix .= '</li><li>'.$paragraph_trip_wire."\n";
                }
                $indent_count_preceding = $indent_count;
            }
           
        } // End of: don't skip this line.
       
        $new_content .= $prefix . $new_line . "\n";
        $prefix = '';
    }// End of: foreach line.
   
    // Close any unclosed lists.
    while(count($list_stack) > 0) {
        $list_popped_off_the_top_of_the_stack = array_pop($list_stack);
        $list_name = $list_popped_off_the_top_of_the_stack[0];
        $new_content .= '</li></'.$list_name.'>';
    }
   
    // Pass the contents to the parser to parse the wikitext etc as per usual.    
    return $wgParser->internalParse($new_content);
   
}
