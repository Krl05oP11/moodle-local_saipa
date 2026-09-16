<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Behat step definitions for local_saipa.
 *
 * @package    local_saipa
 * @category   test
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Steps definitions for local_saipa: the SAIPA chat block, the teacher risk
 * dashboard, and Telegram account linking.
 *
 * @package    local_saipa
 * @category   test
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_saipa extends behat_base {
    /**
     * Converts page names to URLs for the 'I am on the "..." "local_saipa > ..." page' steps.
     *
     * Recognised page types are:
     * | Type              | identifier meaning   | Description                          |
     * | teacher dashboard | course shortname/id  | local/saipa/teacher.php?courseid=... |
     *
     * @param string $type the page type.
     * @param string $identifier identifies the particular page, e.g. a course shortname.
     * @return moodle_url the corresponding URL.
     * @throws Exception if the specified page type or course cannot be resolved.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch ($type) {
            case 'teacher dashboard':
                $courseid = $this->get_course_id($identifier);
                if (!$courseid) {
                    throw new Exception('The specified course "' . $identifier . '" does not exist');
                }
                return new moodle_url('/local/saipa/teacher.php', ['courseid' => $courseid]);

            default:
                throw new Exception('Unrecognised local_saipa page type "' . $type . '".');
        }
    }

    /**
     * Asserts the chat widget's input field is visible.
     *
     * @Then /^I should see the SAIPA chat input field$/
     */
    public function i_should_see_the_saipa_chat_input_field() {
        $this->find('xpath', "//input[starts-with(@id, 'saipa-input-')]");
    }

    /**
     * Types text into the chat widget's input field.
     *
     * @When /^I type "(?P<text>(?:[^"]|\\")*)" into the SAIPA chat input field$/
     * @param string $text
     */
    public function i_type_into_the_saipa_chat_input_field($text) {
        $field = $this->find('xpath', "//input[starts-with(@id, 'saipa-input-')]");
        $field->setValue($this->unescape_step_string($text));
    }

    /**
     * Asserts the chat input field's current value.
     *
     * @Then /^the SAIPA chat input field should contain "(?P<text>(?:[^"]|\\")*)"$/
     * @param string $text
     */
    public function the_saipa_chat_input_field_should_contain($text) {
        $field = $this->find('xpath', "//input[starts-with(@id, 'saipa-input-')]");
        $expected = $this->unescape_step_string($text);
        $actual = $field->getValue();
        if ($actual !== $expected) {
            throw new ExpectationException(
                'The SAIPA chat input field contains "' . $actual . '", expected "' . $expected . '"',
                $this->getSession()
            );
        }
    }

    /**
     * Asserts the browser page title contains the given text.
     *
     * @Then /^I should see "(?P<text>(?:[^"]|\\")*)" in the page title$/
     * @param string $text
     */
    public function i_should_see_in_the_page_title($text) {
        // The <title> element is not rendered/visible, so getText() on it always
        // returns an empty string with the WebDriver backend. Read it from the DOM instead.
        $title = $this->evaluate_script('return document.title;');
        $expected = $this->unescape_step_string($text);
        if (strpos($title, $expected) === false) {
            throw new ExpectationException(
                'The page title is "' . $title . '", which does not contain "' . $expected . '"',
                $this->getSession()
            );
        }
    }

    /**
     * Asserts the teacher dashboard's student list contains the given text.
     *
     * @Then /^I should see "(?P<text>(?:[^"]|\\")*)" in the student table$/
     * @param string $text
     */
    public function i_should_see_in_the_student_table($text) {
        $this->wait_for_pending_js();
        $list = $this->find('css', '#saipa-student-list');
        $expected = $this->unescape_step_string($text);
        if (strpos($list->getText(), $expected) === false) {
            throw new ExpectationException(
                'The SAIPA student table does not contain "' . $expected . '"',
                $this->getSession()
            );
        }
    }

    /**
     * Asserts the current page is Moodle's login page.
     *
     * @Then /^I should be redirected to the login page$/
     */
    public function i_should_be_redirected_to_the_login_page() {
        $url = $this->getSession()->getCurrentUrl();
        if (strpos($url, '/login/index.php') === false) {
            throw new ExpectationException(
                'Expected to be redirected to the login page, current URL is "' . $url . '"',
                $this->getSession()
            );
        }
    }

    /**
     * Simulates a completed risk evaluation via the dashboard's engine-free "Simulate"
     * path (get_course_risk demo mode). The real engine-backed nightly task requires a
     * live external saipa-engine service with no local mock available in this test suite.
     *
     * @When /^the SAIPA risk evaluation has run for course "(?P<course_string>(?:[^"]|\\")*)"$/
     * @param string $coursename unused: the risk evaluation runs against whichever course
     *      the teacher dashboard currently displayed is on.
     */
    public function the_saipa_risk_evaluation_has_run_for_course($coursename) {
        $button = $this->find('css', '#saipa-demo-btn');
        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Asserts risk badges are rendered for the enrolled students.
     *
     * @Then /^I should see risk level badges for the enrolled students$/
     */
    public function i_should_see_risk_level_badges_for_the_enrolled_students() {
        $this->wait_for_pending_js();
        // Polling via find_all(), which throws automatically if nothing matches in time.
        $this->find_all('css', '#saipa-student-list .badge');
    }

    /**
     * Enables the Telegram messaging channel in local_saipa settings.
     *
     * @Given /^the SAIPA Telegram channel is enabled in settings$/
     */
    public function the_saipa_telegram_channel_is_enabled_in_settings() {
        set_config('messaging_channel', 'telegram', 'local_saipa');
    }

    /**
     * Sets local_saipa's messaging channel setting to the given value.
     *
     * @Given /^the SAIPA messaging channel is set to "(?P<value>(?:[^"]|\\")*)"$/
     * @param string $value one of 'none', 'telegram', 'whatsapp', 'both'.
     */
    public function the_saipa_messaging_channel_is_set_to($value) {
        set_config('messaging_channel', $this->unescape_step_string($value), 'local_saipa');
    }

    /**
     * Ensures no Telegram link record exists for student1.
     *
     * @Given /^the student has not linked Telegram$/
     */
    public function the_student_has_not_linked_telegram() {
        global $DB;
        $userid = $this->get_user_id_by_identifier('student1');
        if ($userid) {
            $DB->delete_records('local_saipa_telegram_links', ['userid' => $userid]);
        }
    }

    /**
     * Creates a confirmed Telegram link record for the given user.
     *
     * @Given /^the student "(?P<username>(?:[^"]|\\")*)" has a confirmed Telegram link with username "(?P<tg>(?:[^"]|\\")*)"$/
     * @param string $username Moodle username.
     * @param string $tgusername Telegram username (without the leading @).
     */
    public function the_student_has_a_confirmed_telegram_link($username, $tgusername) {
        global $DB;
        $userid = $this->get_user_id_by_identifier($this->unescape_step_string($username));
        if (!$userid) {
            throw new Exception('The specified user "' . $username . '" does not exist');
        }
        $DB->delete_records('local_saipa_telegram_links', ['userid' => $userid]);
        $now = time();
        $DB->insert_record('local_saipa_telegram_links', (object) [
            'userid'            => $userid,
            'telegram_id'       => random_int(100000000, 999999999),
            'telegram_username' => $this->unescape_step_string($tgusername),
            'confirmed'         => 1,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);
    }

    /**
     * Stubs window.open() so a click that would open the Telegram deep-link in a new
     * tab instead records the URL for inspection — the deep-link is never written to
     * the DOM by the real UI. Must run before the click that triggers it.
     *
     * @Given /^I install a Telegram deep-link listener$/
     */
    public function i_install_a_telegram_deep_link_listener() {
        $this->execute_script(
            'window.open = function(url) { window.__saipaLastDeepLink = url; return null; };'
        );
    }

    /**
     * Asserts the stubbed deep-link listener captured a URL with the given prefix.
     *
     * @Then /^I should see a Telegram deep-link starting with "(?P<prefix>(?:[^"]|\\")*)"$/
     * @param string $prefix
     */
    public function i_should_see_a_telegram_deep_link_starting_with($prefix) {
        $this->wait_for_pending_js();
        $expected = $this->unescape_step_string($prefix);
        $link = $this->evaluate_script('return window.__saipaLastDeepLink;');
        if (!$link || strpos($link, $expected) !== 0) {
            throw new ExpectationException(
                'Expected a Telegram deep-link starting with "' . $expected . '", got "' . $link . '"',
                $this->getSession()
            );
        }
    }

    /**
     * Undoes Behat/Gherkin's escaping of double quotes inside a step's quoted string.
     *
     * @param string $text
     * @return string
     */
    protected function unescape_step_string(string $text): string {
        return str_replace('\"', '"', $text);
    }
}
