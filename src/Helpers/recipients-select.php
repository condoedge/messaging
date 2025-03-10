<?php 

use Condoedge\Messaging\Models\CustomInbox\EmailAccount;

/* ELEMENTS */
function _RecipientsMultiSelect()
{
    return _MultiSelect()->placeholder('messaging-search-recipients')->class('recipients-multiselect mb-2')
        ->name('recipients', false)
        ->searchOptions(2, 'searchRecipients', 'retrieveRecipients');
}

function _CcToggle()
{
    return _Rows(
        _Link('messaging-cc-bcc')->toggleId('cc-bb-recipients')->class('mb-2 text-gray-700 text-xs'),
        _Rows(
            _CcRecipientsMultiSelect(),
            _BccRecipientsMultiSelect(),
        )->id('cc-bb-recipients'),
    );
}

function _CcRecipientsMultiSelect()
{
    return _MultiSelect()->placeholder('messaging-cc')->class('recipients-multiselect mb-2')
        ->name('cc_recipients', false)
        ->searchOptions(2, 'searchRecipients', 'retrieveRecipients');
}

function _BccRecipientsMultiSelect()
{
    return _MultiSelect()->placeholder('messaging-bcc')->class('recipients-multiselect mb-4')
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
		$email => $entity?->getEmailOption() ?: $email,
	];
}

function checkRecipientsAreValid()
{
    $emails = getRequestRecipients();

    $invalidEmails = collect($emails)->filter(fn($email) => !isValidEmail($email));

    if ($invalidEmails->count()) {
        abort(403, '"'.$invalidEmails->first().'" '.__('error-is-not-a-valid-email-address-please-correct-it'));
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
        $recipients = ThreadGroupsForm::getMatchingRecipientOptions($group);
        return $recipients->keys();
    }

    return request('recipients');
}

function getCcRecipients()
{
    return request('cc_recipients');
}

function getBccRecipients()
{
    return request('bcc_recipients');
}
