=== Gravity Forms Personality Quiz Add-On ===
Contributors: dabernathy89
Tags: gravity forms, quiz
Requires at least: 3.9
Tested up to: 4.0
Stable tag: trunk
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

The Personality Quiz add-on for Gravity Forms lets you create simple, un-graded personality quizzes (think Buzzfeed-style quizzes).

== Description ==
The Personality Quiz add-on for Gravity Forms lets you create simple, un-scored personality quizzes (think Buzzfeed-style quizzes).

While there is an official quiz add-on for Gravity Forms, it is focused on graded quizzes like those you might take in school. This add-on lets you easily create quizzes that return a result rather than a grade, like "How Texan are you?" or "What Disney character would you be?"

In addition to setting up the logic for these quizzes, this add-on also implements the WordPress media uploader to make it incredibly simple to use images as labels for questions and answers. The add-on includes some styles to make your quizzes look good out of the box, but these can be toggled on or off for each quiz.

= Setting up your quiz =

You can build two types of quizzes: numeric and multiple choice. The result for a numeric quiz will be a number, while the result for a multiple choice quiz will be one of the choices present in your form. Only radio and checkbox inputs with the "Use for Personality Quiz Score" option checked will be used to calculate a result. You can use Gravity Forms' conditional logic to provide different confirmations or notifications based on the quiz result.

**Numeric quizzes** are scored by adding the values of radio and checkbox inputs in your form and producing a total. The values associated with these inputs must be numeric.

**Multiple choice** quizzes produce a text result rather than a numeric result. The add-on will check to see which value among the inputs was selected most often by the user, and will return that value as the quiz result. Ties will be broken randomly.

== Installation ==
Install from the WordPress dashboard, or upload the unzipped folder to your plugins directory.

== Changelog ==
0.1 - initial plugin