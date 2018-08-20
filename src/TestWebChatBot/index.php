<!DOCTYPE HTML>
<html>
    <head>
        <title>
            BBot Test Web Chatbot
        </title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <style type="text/css">
            #responseHolder {
                min-width: 600px;
                min-height: 300px;
                width: 80%;
                height: 500px;
                overflow: auto;
                margin: 10px auto;
                background-color: oldlace;
            }

        </style>
    </head>
    <body>
        <div id="responseHolder"></div>
        <form id="frmChat" action="#">

            <table>
                <tr>
                    <td>BBot RESTful URL:</td>
                    <td>
                        <input type="text" id="url" value="http://domain/Channels/RESTfulWebService/" size="70"   />
                    </td>
                </tr>
                <tr>
                    <td>Bot ID:</td>
                    <td>
                        <input type="text" id="bot_id" value="7" autocomplete="off"  />
                    </td>
                </tr>
                <tr>
                    <td>User ID:</td>
                    <td>
                        <input type="text" id="user_id" name="username" size="20" value="joe" autocomplete="off"  />                                               
                    </td>
                </tr>
                <tr>
                    <td>Message:</td>
                    <td><input type="text" id="msg" size="70" autocomplete="off"  /></td>
                </tr>
                <tr>
                    <td colspan="2"><input type="submit" name="send" value="Send Value" /></td>
                </tr>
            </table>
            <input type="hidden" name="send" />
        </form>

        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
        <script type="text/javascript">

            var botName = 'bbot';		// change this to your bot name

            // declare timer variables
            var alarm = null;
            var callback = null;
            var loopback = null;

            $(function () {
                $('#frmChat').submit(function (e) {

                    e.preventDefault();


                    var data = {
                        'orgId': 1,
                        'botId': $('#bot_id').val(),
                        'userId': $('#user_id').val(),
                        'input': {
                            'text': $('#msg').val()
                        },
                        'runBot': true
                    };

                    var chatLog = $('#responseHolder').html();
                    var youSaid = '<strong>' + $('#user_id').val() + ':</strong> ' + $('#msg').val() + "<br>\n";
                    update(youSaid);



                    sendMessage(data);
                    $('#msg').val('').focus();
                });


                function sendMessage(data) {
                    console.log(data);
                    console.log(JSON.stringify(data));
                    $.ajax({
                        url: $('#url').val(),
                        /*dataType: 'json',*/
                        contentType: 'application/json',
                        data: JSON.stringify(data),
                        type: 'POST',
                        success: function (response) {
                            processResponse(response);
                        },
                        error: function (xhr, status, error) {
                            alert('oops? Status = ' + status + ', error message = ' + error + "\nResponse = " + xhr.responseText);
                        }
                    });
                }


                function update(text) {
                    var chatLog = $('#responseHolder').html();
                    $('#responseHolder').html(chatLog + text);
                    var rhd = $('#responseHolder');
                    var h = rhd.get(0).scrollHeight;
                    rhd.scrollTop(h);
                }

                function processResponse(response) {

                    var data = JSON.parse(response);
                    console.log(data);
                    console.log(response);

                    if (data.error) {
                        data.output = {text: 'Error from BBot'};
                    }

                    var botSaid = '<strong>' + botName + ':</strong> ' + data.output.text + "<br>\n";
                    update(botSaid);
                }
            });

        </script>
    </body>
</html>
