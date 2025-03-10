<?php 

namespace Condoedge\Messaging\Kompo\CustomInbox;

trait RecipientsMultiselectTrait
{
	public function searchRecipients($search)
	{
		return searchRecipientsMultiselect($search);
	}

	public function retrieveRecipients($email)
	{
		return retrievedRecipientsMultiselect($email);
	}
}