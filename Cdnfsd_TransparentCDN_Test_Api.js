jQuery(document).ready(function($) {

  box = document.getElementById('tcdn_test_status')
  box.innerHTML = transparent_configuration_strings.test_string
  $('#transparentcdn_test').on('click', function(e) {
     e.preventDefault()
    p = document.getElementById('tcdn_test_text')

    url = "https://api.transparentcdn.com/v1/oauth2/access_token/"
    client_id="client_id"+"="+document.getElementById('cdnfsd_transparentcdn_clientid').value
    client_secret="client_secret"+"="+document.getElementById('cdnfsd_transparentcdn_clientsecret').value
    grant_type="grant_type"+"="+"client_credentials"
  
    params = grant_type+"&"+client_id+"&"+client_secret
    req = new XMLHttpRequest()
    req.open("POST", url, true)
    req.setRequestHeader('Content-type', 'application/x-www-form-urlencoded')
    req.onreadystatechange = function(e) {
        if (req.readyState == 4){
            if (req.status == 200){
                box.innerHTML = transparent_configuration_strings.test_success
                box.className = "w3tc-status w3tc-success" 
                console.log(req.responseText)
            } else {
                box.innerHTML = transparent_configuration_strings.test_failure
                box.className = "w3tc-status w3tc-error"
                console.log(req.responseText)
            }
        }
    }
    req.send(params)
  })
})