<?php 

use Condoedge\Messaging\Models\CustomInbox\EmailAccount;

/* ELEMENTS */
function _RecipientsMultiSelect()
{
    return _MultiSelect()->placeholder('messaging.search-recipients')->class('recipients-multiselect mb-2')
        ->name('recipients', false)
        ->searchOptions(2, 'searchRecipients', 'retrieveRecipients');
}

function _CcToggle()
{
    return _Rows(
        _Link('CC / BCC')->toggleId('cc-bb-recipients')->class('mb-2 text-gray-700 text-xs'),
        _Rows(
            _CcRecipientsMultiSelect(),
            _BccRecipientsMultiSelect(),
        )->id('cc-bb-recipients'),
    );
}

function _CcRecipientsMultiSelect()
{
    return _MultiSelect()->placeholder('cc:')->class('recipients-multiselect mb-2')
        ->name('cc_recipients', false)
        ->searchOptions(2, 'searchRecipients', 'retrieveRecipients');
}

function _BccRecipientsMultiSelect()
{
    return _MultiSelect()->placeholder('bcc:')->class('recipients-multiselect mb-4')
        ->name('bcc_recipients', false)
        ->searchOptions(2, 'searchRecipients', 'retrieveRecipients');
}

/* ACTIONS */
function searchRecipientsMultiselect($search = '')
{
	$existing = EmailAccount::where('email_adr', 'LIKE', '%'.$search.'%')->get();

	if (!$existing->count() && filter_var($search, FILTER_VALIDATE_EMAIL)){
		$newEmail = new EmailAccount();
		$newEmail->email_adr = $search;
		$existing = $existing->concat(collect([$newEmail]));
	}

	return $existing->mapWithKeys(
		fn($ea) => [
			$ea->email_adr => $ea->getEmailOption()
		]
	);
}

function retrievedRecipientsMultiselect($email)
{
	$entity = EmailAccount::where('email_adr', $email)->first();

	return [
		$email => $entity->getEmailOption()
	];
}

function checkRecipientsAreValid()
{
    $emails = getRequestRecipients();

    $invalidEmails = collect($emails)->filter(fn($email) => !isValidEmail($email));

    if ($invalidEmails->count()) {
        abort(403, '"'.$invalidEmails->first().'" '.__('is not a valid email address! Please correct it and try again.'));
    }
}

function isValidEmail($email)
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
}

/* CALCULATED FIELDS */
function getRequestRecipients()
{
    if ($group = request('massive_recipients_group')) {
        $recipients = ThreadGroupsForm::getMatchingRecipients($group);
        return RecipientsMultiSelect::getValidEmailOptionsFromGroup($recipients, $group);
    }

    return request('recipients');
}