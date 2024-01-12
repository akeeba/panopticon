@include('Mailtemplates/mail_action_summary', [
    'site'    => $this->getContainer()->mvcFactory->makeTempModel('Sites')->findOrFail($this->getModel()->getState('site_id', null)),
    'records' => $this->getModel()->get(true),
    'start'   => $this->getModel()->getState('from_date'),
    'end'     => $this->getModel()->getState('to_date')
])