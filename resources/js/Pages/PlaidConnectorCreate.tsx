import React from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import { usePlaidLink } from 'react-plaid-link'
import PrimaryButton from '@/Jetstream/Components/PrimaryButton'
import TextInput from '@/Jetstream/Components/TextInput'
import InputLabel from '@/Jetstream/Components/InputLabel'

interface PageProps {
  teams: any
  plaidLinkToken: any
  plaidRoutes: any
}

const PlaidConnectorCreate: React.FC<PageProps> = ({ teams, plaidLinkToken, plaidRoutes }) => {

  const form = useForm({
    public_token: null,
    name: '',
    institution_name: null,
    plaid_institution_id: null,
    plaid_link_token_id: null
  })

  let config = {
    token: plaidLinkToken,
    onExit: (err: any, metadata: any) => (
      console.log('Closed', err, metadata)
    ),
    onSuccess: (public_token: any, metadata: any) => {
      form.setData({
        public_token: public_token,
        name: metadata.institution.name,
        institution_name: metadata.institution.name,
        plaid_institution_id: metadata.institution.institution_id,
        plaid_link_token_id: plaidLinkToken,
      })
      form.post(plaidRoutes.store)
    }
  }

  const { open, exit, ready } = usePlaidLink(config)

  return (
    <div>
      <Head title="Connect Account" />

      {form.data.public_token !== null ? (
        <>Redirecting...</>
      ) : (
        <PrimaryButton type="button" onClick={() => open()} disabled={!ready}>
          Connect Account
        </PrimaryButton>
      )}
    </div>
  )
}

export default PlaidConnectorCreate
