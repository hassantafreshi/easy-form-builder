<?php
/**
 * PUB-03 (B5 → B7 hand-off): the texts of the ready-made form templates (includes/admin/assets/js/forms-efb.js:
 * field labels, help texts, hints and choices) as phrases of the phrase list, so a form made from a template is in
 * the site's language. Merged into efb_var.text on the Create screen only (efbFunction::efb_sentence_forms(), the
 * text_efb(1) set). forms-efb.js reads them with efbTplT(key, English). Generated from forms-efb.js; keep the two in
 * step (tests/test-template-phrases.php).
 *
 * @package EasyFormBuilder
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('emsfb_template_phrases_efb')) {
	/**
	 * @return array<string, string> key => text (plain text: the builder writes it into the form's JSON).
	 */
	function emsfb_template_phrases_efb() {
		$t = array(
		/* translators: ready-made form template: a field label */
		'tplMessageIssues' => __('Message / Issues', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplStep1' => __('Step 1', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplStep2' => __('Step 2', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplMessage' => __('Message…', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplContactUsForm' => __('Contact us form', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplAddress' => __('Address', 'easy-form-builder'),
		/* translators: ready-made form template: a field label / a step title */
		'tplVehicle' => __('Vehicle', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplSalonConsultationForm' => __('Salon Consultation Form', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplSalonService' => __('Salon service', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplForm' => __('FORM', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplDesignType' => __('Design Type', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplCvSubmission' => __('CV Submission', 'easy-form-builder'),
		/* translators: ready-made form template: text under a step title */
		'tplDoYouWantToWork' => __('Do you want to work with us? Please fill in your details below.', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplUploadYourCvHere' => __('Upload your CV here', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplUploadCoverLetter' => __('Upload cover letter', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplBrief' => __('BRIEF', 'easy-form-builder'),
		/* translators: ready-made form template: text under a step title */
		'tplVideographyCreativeBrief' => __('VIDEOGRAPHY CREATIVE BRIEF', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplYourEmail' => __('Your Email', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplCompanyName' => __('Company Name', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplInstagramComYourprofile' => __('instagram.com/yourprofile', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplInstagramAddress' => __('Instagram Address', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplUrl' => __('URL', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplWhatTypeOfContentAre' => __('What type of content are you looking to create?', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplProductVideo' => __('Product video', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplProductVideoWithModel' => __('Product video with model', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplOnLocationOutside' => __('On-Location / Outside', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplWhatIsTheLengthOf' => __('What is the length of the video', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplUpTo1Min' => __('Up to 1 min', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplMoreThan1Min' => __('More than 1 min', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplLongText' => __('Long Text', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplPleaseListAnyProductsOr' => __('Please list any products or services you would like featured in the video(s)', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplProductsOrServices' => __('Products or Services', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplWhatIsTheDeadlineFor' => __('What is the deadline for receiving the video?', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplDeadline' => __('Deadline', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplWhatWillThisVideoBe' => __('What will this video be primarily used for?', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplSocialMediaFeedPosts' => __('Social media feed posts', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplWebsiteProductPageVideos' => __('Website Product Page videos', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplAdsVideo' => __('Ads Video', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplIfYouRequireAModel' => __('If you require a model for your shoot', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplYesIDLikeYou' => __('Yes, I\'d like you to find a model for me', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplIWillSourceMyModel' => __('I will source my model', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplWhatIsYourBudgetFor' => __('What is your budget for this project?', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplBudget' => __('Budget', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplPartyInvite' => __('Party Invite', 'easy-form-builder'),
		/* translators: ready-made form template: text under a step title */
		'tplWeReHavingAParty' => __('We\'re having a party and would love to see you. Please let us know if you can come.', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplHowManyOfYouAre' => __('How many of you are attending?', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplCanYouAttend' => __('Can you attend?', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplYesIWillBeThere' => __('Yes, I will be there', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplSorryICanTMake' => __('Sorry, I can\'t make it', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplLetUsKnowWhatKind' => __('Let us know what kind of dish(es) you will be bringing', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplWhatWillYouBeBringing' => __('What will you be bringing?', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplEventRegistration' => __('Event registration', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplWhatDaysWillYouAttend' => __('What days will you attend?', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplDietaryRestrictions' => __('Dietary restrictions', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplIUnderstandThatIWill' => __('I understand that I will have to pay $$ upon arrival', 'easy-form-builder'),
		/* translators: ready-made form template: a heading */
		'tplWeWouldBeHappyTo' => __('We would be happy to hear your feedback on how we can improve your experience!', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplFeedbackType' => __('Feedback Type', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplFeedback' => __('Feedback', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplInfo' => __('Info', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplOrder' => __('Order', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplMyProducts' => __('My Products', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplNewProduct110' => __('New Product 1 (10$)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplNewProduct220' => __('New Product 2 (20$)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplNewProduct330' => __('New Product 3 (30$)', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplInStoreExperienceSurveyTemplate' => __('In-Store Experience Survey Template', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplEmail' => __('Email', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplTel' => __('Tel', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplDateOfLastStoreVisit' => __('Date of Last Store Visit', 'easy-form-builder'),
		/* translators: ready-made form template: a heading */
		'tplPleaseRespondToTheFollowing' => __('Please respond to the following:', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplIWasGreetedUponEntering' => __('I was greeted upon entering the store', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplSomeoneOfferedToHelpMe' => __('Someone offered to help me', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplStorePersonnelWereFriendlyAnd' => __('Store personnel were friendly and polite', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplTheStoreWasClean' => __('The store was clean', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplMerchandiseWasFoldedOrOrganized' => __('Merchandise was folded or organized neatly', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplMyItemsWereEasyTo' => __('My items were easy to locate', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplTheStoreAtmosphereWasWelcoming' => __('The store atmosphere was welcoming', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplFittingRoomsWereCleanAnd' => __('Fitting rooms were clean and free of discarded merchandise', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplTheStoreLightingWasAppropriate' => __('The store lighting was appropriate (not too dark or too bright)', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplTheMusicInTheStore' => __('The music in the store was appropriate (not too loud or too quiet)', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplPricesWereFairComparedTo' => __('Prices were fair compared to other similar stores', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplCommentOrMessage' => __('Comment or Message', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplHowLikelyAreYouTo' => __('How likely are you to recommend us to others?', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplVoterBehaviorSurveyTemplate' => __('Voter Behavior Survey Template', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplAreYouRegisteredToVote' => __('Are you registered to vote?', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplInWhichOfTheFollowing' => __('In which of the following elections have you participated in the last 4 years? Select all that apply.', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplPresidentialElection' => __('Presidential Election', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplMidtermElection' => __('Midterm Election', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplStateElection' => __('State Election', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplLocalElectionCouncilMayorSchool' => __('Local Election (council, mayor, school board, etc.)', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplHowDoYouGetInformation' => __('How do you get information about candidates to determine who gets your vote? Select all that apply.', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplAttendTownHalls' => __('Attend town halls', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplReadCandidateWebsites' => __('Read candidate websites', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplFollowCandidatesOnSocialMedia' => __('Follow candidates on social media', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplWatchCandidateDebates' => __('Watch candidate debates', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplReadCandidateLiterature' => __('Read candidate literature', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplAttendCandidateRallies' => __('Attend candidate rallies', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplTvRadioCommercials' => __('TV/Radio commercials', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplReadPastVotingRecordsOr' => __('Read past voting records or other important historical data', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplThroughMyRegisteredPartyS' => __('Through my registered party\'s recommendations', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplOther' => __('Other', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplWhichMethodsOfVotingHave' => __('Which methods of voting have you used in the past? Select all that apply.', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplEarlyVoting' => __('Early voting', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplMailInBallot' => __('Mail-in ballot', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplInPersonVoting' => __('In-person voting', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplMessage2' => __('Message', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplSummerReadingProgramSignupForm' => __('Summer Reading Program Signup Form Template', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplChildSName' => __('Child\'s Name', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplChildSDateOfBirth' => __('Child\'s Date of Birth', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplInWhichLevelWillThe' => __('In which level will the child be participating?', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplPreKAges05' => __('Pre-K (ages 0-5)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplKidsAges57' => __('Kids (ages 5-7)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplYouthAges812' => __('Youth (ages 8-12)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplTeenAges1316' => __('Teen (ages 13-16)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplYoungAdultAges1718' => __('Young Adult (ages 17-18)', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplParentGuardianName' => __('Parent/Guardian Name', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplParentGuardianEmail' => __('Parent/Guardian Email', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplMobileNumber' => __('Mobile Number', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplComment' => __('Comment', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplRecLeagues18' => __('Rec Leagues (18+)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplKickballTuesThurs' => __('Kickball (Tues/Thurs)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplBasketballMonWed' => __('Basketball (Mon/Wed)', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplRecreationalSportsLeagueSignupForm' => __('Recreational Sports League Signup Form Template', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplMobilePhone' => __('Mobile Phone', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplDateOfBirth' => __('Date of Birth', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplTShirtSizeUnisex' => __('T-shirt Size (unisex)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplXs' => __('XS', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplXl' => __('XL', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplXxl' => __('XXL', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplEmergencyContactName' => __('Emergency Contact Name', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplEmergencyContactPhone' => __('Emergency Contact Phone', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplEmergencyContactEmail' => __('Emergency Contact Email', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplGamesTakePlaceOnThe' => __('Games take place on the days listed at 6, 7, or 8 p.m. You\'ll receive a schedule for each team or tournament.', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplShuffleboardTeamOf2Mon' => __('Shuffleboard Team of 2 (Mon)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplBocceMon' => __('Bocce (Mon)', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplCornholeThurs' => __('Cornhole (Thurs)', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplAgreement' => __('Agreement', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplIHaveReceivedAndRead' => __('I have received and read the terms and waiver and agree to all.', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplParentGuardianPhone' => __('Parent/Guardian Phone', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplContactUsForm2' => __('Contact-us Form', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplCity' => __('City', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplDoesTheChildResideWith' => __('Does the child reside with you (at least part-time in cases of custody arrangements)', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplIfNoPinIsEntered' => __('If no PIN is entered here, your library card PIN will be set to the last 4 numbers of your phone number.', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplSelectA4DigitPin' => __('Select a 4-digit PIN number', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplIHaveReadAndAgree' => __('I have read and agree to the library\'s terms of use and understand that any violation is grounds for revocation.', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplIGivePermissionForMy' => __('I give permission for my child to receive a library card.', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplEmployeeSuggestionFormTemplate' => __('Employee Suggestion Form Template', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplDepartment' => __('Department', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplRoleTitle' => __('Role/Title', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplYourSuggestionIsRegardingHellip' => __('Your suggestion is regarding&hellip;', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplEmployeeExperience' => __('Employee Experience', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplCustomerExperience' => __('Customer Experience', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplPleaseExplainYourSuggestionFor' => __('Please explain your suggestion for how we can improve the employee experience.', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplBookClubSuggestionFormTemplate' => __('Book Club Suggestion Form Template', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplBookSuggestion' => __('Book Suggestion', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplAuthor' => __('Author', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplGenre' => __('Genre', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplPageCount' => __('Page Count', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplPleaseProvideABriefSynopsis' => __('Please provide a brief synopsis of the book for us to share with the group.', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplContactInformation' => __('Contact Information', 'easy-form-builder'),
		/* translators: ready-made form template: text under a step title */
		'tplPleaseProvideYourContactDetails' => __('Please provide your contact details', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplCompanyOrganization' => __('Company / Organization', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplQuoteDetails' => __('Quote Details', 'easy-form-builder'),
		/* translators: ready-made form template: text under a step title */
		'tplTellUsAboutYourProject' => __('Tell us about your project requirements', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplSelectAllServicesYouNeed' => __('Select all services you need', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplServicesRequired' => __('Services Required', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplWebDesign' => __('Web Design', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplWebDevelopment' => __('Web Development', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplGraphicDesign' => __('Graphic Design', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplSeoMarketing' => __('SEO / Marketing', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplConsulting' => __('Consulting', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplBudgetRange' => __('Budget Range', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplUnder500' => __('Under $500', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplWhenDoYouNeedThis' => __('When do you need this delivered?', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplDesiredDeliveryDate' => __('Desired Delivery Date', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplUploadBriefsSpecsOrReference' => __('Upload briefs, specs, or reference files', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplUploadProjectFiles' => __('Upload Project Files', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplDescribeYourProjectInDetail' => __('Describe your project in detail&hellip;', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplProjectDescription' => __('Project Description', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplCustomerInformation' => __('Customer Information', 'easy-form-builder'),
		/* translators: ready-made form template: text under a step title */
		'tplPleaseProvideYourContactAnd' => __('Please provide your contact and shipping details', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplShippingAddress' => __('Shipping Address', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplPostalZipCode' => __('Postal / ZIP Code', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplPostalCode' => __('Postal Code', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplOrderDetails' => __('Order Details', 'easy-form-builder'),
		/* translators: ready-made form template: text under a step title */
		'tplSelectYourProductsAndQuantities' => __('Select your products and quantities', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplProductService' => __('Product / Service', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplProductAStandard' => __('Product A - Standard', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplProductBPremium' => __('Product B - Premium', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplProductCEnterprise' => __('Product C - Enterprise', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplCustomServicePackage' => __('Custom Service Package', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplEnterTheNumberOfItems' => __('Enter the number of items', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplQuantity' => __('Quantity', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplPreferredDeliveryDate' => __('Preferred Delivery Date', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplPaymentMethod' => __('Payment Method', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplCreditCardStripe' => __('Credit Card / Stripe', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplPaypal' => __('PayPal', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplBankTransfer' => __('Bank Transfer', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplInvoiceLater' => __('Invoice Later', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplAnySpecialInstructionsOrCustomization' => __('Any special instructions or customizations&hellip;', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplSpecialRequirementsNotes' => __('Special Requirements / Notes', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplPersonalInformation' => __('Personal Information', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplEGSoftwareDeveloperDesigner' => __('e.g. Software Developer, Designer, Manager&hellip;', 'easy-form-builder'),
		/* translators: ready-made form template: a step title */
		'tplApplicationDetails' => __('Application Details', 'easy-form-builder'),
		/* translators: ready-made form template: text under a step title */
		'tplUploadYourDocumentsAndProvide' => __('Upload your documents and provide additional information', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplWhenCanYouStart' => __('When can you start?', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplEarliestAvailableStartDate' => __('Earliest Available Start Date', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplExpectedSalaryRange' => __('Expected Salary Range', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplUnder30000' => __('Under $30,000', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplNegotiable' => __('Negotiable', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplEmploymentType' => __('Employment Type', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplFullTime' => __('Full-Time', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplPartTime' => __('Part-Time', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplFreelanceContract' => __('Freelance / Contract', 'easy-form-builder'),
		/* translators: ready-made form template: a choice */
		'tplInternship' => __('Internship', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplAcceptedFormatsPdfDocDocx' => __('Accepted formats: PDF, DOC, DOCX', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplUploadResumeCv' => __('Upload Resume / CV', 'easy-form-builder'),
		/* translators: ready-made form template: help text under a field */
		'tplOptionalUploadYourCoverLetter' => __('Optional - upload your cover letter', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplUploadCoverLetter2' => __('Upload Cover Letter', 'easy-form-builder'),
		/* translators: ready-made form template: the hint inside an empty field */
		'tplTellUsWhyYouAre' => __('Tell us why you are a great fit for this position&hellip;', 'easy-form-builder'),
		/* translators: ready-made form template: a field label */
		'tplWhyShouldWeHireYou' => __('Why Should We Hire You?', 'easy-form-builder'),
		);
		return array_map('wp_strip_all_tags', $t);
	}
}
