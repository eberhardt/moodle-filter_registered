<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Filter for generating collapsible regions easily
 *
 * @package    filter_collapsible
 * @copyright  2015 Jan Eberhardt <eberhardt@math.tu-berlin.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once ($CFG->dirroot . "/mod/registration/lib.php");

/**
 * Collapsible region filter
 *
 * @author Jan Eberhardt <eberhardt@math.tu-berlin.de>
 * @copyright 2015 onwards
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_registered extends moodle_text_filter {

	/** @const TAG Codeword, which initializes this filter */
    const TAG = '{registered}';

    /** @const NaN Not a Number - used to imply false usage of this filter */
    const NaN = -1;

    /**
     * Apply the filter to the text
     *
     * @see filter_manager::apply_filter_chain()
     * @param string $text to be processed by the text
     * @param array $options filter options
     * @return string text after processing
     */
    public function filter($text, array $options = array()) {
        if (strpos($text, self::TAG) !== false) {
        	// Find the first occurence only.
            $this->replace_registered($text);
        }
        return $text;
    }

    /**
     * Replace tags with actual HTML.
     *
     * @param string $text
     */
    private function replace_registered(&$text) {
    	global $DB, $CFG, $USER;
        $replaced = '';
        $parts = explode(self::TAG, $text); // Apply filter for each tag in text (there may be two or more in one).
        $text = array_shift($parts); // Remove the leading part from array.
        foreach ($parts as $tail) {
        	// Add number of submissions in front of each other part.
        	$cm = preg_match('/^\[([\d]+)\](.*)/', $tail, $match) ? clean_param($match[1], PARAM_INT) : self::NaN;
        	if ($cm === self::NaN) {
				$text .= $this->brackets(get_string("usage", "filter_registered")) . $tail;
			} else {
				$tail = $match[2];
				$cm = $DB->get_record("course_modules", array("id" => $cm));
				$ctx = context_module::instance($cm->id, IGNORE_MISSING);
				$regid = $ctx ? $DB->get_field("course_modules", "instance", array("id" => $cm->id)) : false;
				$registration = $regid ? $DB->get_record("registration", array("id" => $regid)) : false;
				if ($ctx && $regid && $registration) {
					if (!has_capability("mod/registration:view", $ctx)) {
						// Show link, if not subscribed to course.
						// May lead to confusion, if rights are edited by teachers, because message can ocour,
						// even if subscribed to the course. We accept that singular situation.
						$text .= html_writer::link(new moodle_url("/enrol/index.php", array("id" => $cm->course)),
						                           get_string("subscribetounlock", "filter_registered"))
						       . $tail;
					} else if ($registration->timeavailable < time()) {
						// Registration is not available anymore.
						$text .= get_string("closed", "filter_registered");
					} else if ($pos = registration_get_position_in_list($regid, $USER->id)) {
						// User is allready subscribed: vary between `in queue` an active registration.
						$prior = $registration->number - $pos;
						if ($prior < 0) {
							$text .= get_string("waitingfor", "filter_registered", -1*$prior);
						} else {
							$text .= get_string("registeredalready", "filter_registered");
						}
						$text .= $tail;
					} else {
						// User is not subscribed: vary between free slots available and queue
						$free = $registration->number - registration_count_submissions($registration);
						if ($free > 0) {
							$text .= get_string("freeslots", "filter_registered", $free);
						} else if ($registration->allowqueue) {
							$text .= get_string("inqueue", "filter_registered", -1*$free);
						} else {
							$text .= get_string("fullybooked", "filter_registered");
						}
						$text .= $tail;
					}
		        } else {
		            $text .= $this->brackets(get_string("invalidmoduleid", "error", $cm->id)) . $tail;
		        }
			}
        }
    }

    /**
     * Returns text with brackets for notes/error messages
     *
     * @param string $string
     * @return string
     */
    private function brackets($string) {
    	return "[[" . $string . "]]";
    }
}
