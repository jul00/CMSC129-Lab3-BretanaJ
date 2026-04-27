<!DOCTYPE html>
<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 80px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-dark bg-dark p-3 fixed-top">
        <div class="container-fluid">
            <a href="/" class="navbar-brand">moviewatchd</a>
            <a href="/trash" class="btn btn-warning">Trash</a>
        </div>
    </nav>

    <div class="container mt-6 pt-4">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @yield('content')
    </div>

    <button onclick="toggleChat()" class="btn btn-primary"
    style="position:fixed; bottom:20px; right:20px;">
    💬
    </button>

    <div id="chatbox" style="
    position:fixed;
    bottom:80px;
    right:20px;
    width:300px;
    background:white;
    border:1px solid #ccc;
    display:none;
    padding:10px;
    ">

    <div id="messages" style="height:200px; overflow:auto;"></div>
        <input id="msg" class="form-control mt-2">
        <button onclick="send()">Send</button>
    </div>

    <script>
        function toggleChat(){
            let box = document.getElementById('chatbox');
            console.log("clicked");
            box.style.display = box.style.display === 'none' ? 'block' : 'none';
        }

        function send(){
            let msg = document.getElementById('msg').value;

            document.getElementById('messages').innerHTML += `<p><b>You:</b> ${msg}</p>`;

            document.getElementById('msg').value = '';

            fetch('/chat',{
                method:'POST',
                headers:{
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN':'{{ csrf_token() }}'
                },
                body: JSON.stringify({message:msg})
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('messages').innerHTML +=
                    `<p><b>AI:</b> ${data.reply}</p>`;
                if (data.refresh) {
                    location.reload();
                }
            })

            .catch(error => {
                document.getElementById('messages').innerHTML +=
                    `<p style='color:red;'>Error connecting to server</p>`;
            });


        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
