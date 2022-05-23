const express = require('express');
const app = express();
const http = require('http');
const server = http.createServer(app);
const { Server } = require('socket.io');
const io = new Server(server);
const fs = require('fs');
const request = require('request');
const ip = require('ip');
const apiPath = Buffer.from('aHR0cHM6Ly91MTkzMTQ0ODY6RnJAbm5hOCpAd2hlYXRsZXkuY3MudXAuYWMuemEvdTE5MzE0NDg2L0NPUzIxNi9Ib21ld29yay9hcGkucGhw', 'base64').toString();
const users = {};

app.use(express.json());
app.use(express.static(__dirname + '/node_modules'));

console.log("=> Enter server port: ");

var Stdin = process.openStdin();
var port;
var isPort = true;
Stdin.addListener("data", (data) => {
    var input = data.toString().trim();
    if (isPort) {
        port = input;
        server.listen(port);
        console.log("+ -------------------------------");
        console.log("| Listening on " + ip.address() + ":" + port);
        console.log("| ------------------------------- ");
        console.log("| No users");
        console.log("+ -------------------------------");
        isPort = false;
    } else {
        input = input.toUpperCase()
        if (input == "LIST") {
            console.log("=== Server connections ===");
            LIST();
        }

        if (input.substring(0, 4) == "KILL") {
            KILL(input.substring(5, input.length));
        }

        if (input == "QUIT") {
            QUIT();
        }
    }
});

function LIST() {
    for (i = 0; i < Users.length; i++) {
        if (Clients.indexOf(Users[i]) >= 0) {
            console.log("| • AUTHENTICATED Connection " + i + " From " + Users[i].api);
        } else {
            console.log("| • Connection " + i + " From " + Users[i].id);
        }
    }
}

function KILL(connectionNo) {
    if ((typeof Users[connectionNo] == 'undefined')) //check array bounds
    {
        console.log("+ -------------------------------");
        console.log("| Invalid connection number");
        console.log("+ -------------------------------");
    } else {
        Users[connectionNo].emit("bye");
        Users[connectionNo].disconnect();
        console.log("| Connection " + connectionNo + " successfully killed");
        console.log("+ -------------------------------");
    }
}

function QUIT() {
    for (i = 0; i < Users.length; i++) {
        Users[i].emit("bye");
        Users[i].disconnect();
        i--;
    }
    console.log("-> SERVER SHUTDOWN <-");
    console.log("+ -------------------------------");
    server.close();
    process.exit(0);
}

var lTimestamp = 0;

var Users = [];
var Clients = [];

io.on('connection', (socket) => {
    console.log('a user connected');
    console.log('User connected: ' + socket.id);
    Users.push(socket);

    socket.on('authorise', apikey => {
        socket["apikey"] = apikey;
        Clients.push(socket);
        console.log('Client authorised: ' + apikey);
    });

    socket.on('logon', id => {
        var apikey = socket.apikey;
        socket["id"] = id;

        getComments(apikey, id, res => {
            if (res.status == 'failed') {
                lTimestamp = Date.now();
                var comments = '';
                saveChats(apikey, id, comments, () => {
                    socket['chat'] = comments;
                    for (let i = 0; i < Clients.length; i++) {
                        if (Clients[i].apikey == apikey && Clients[i].saved - comments != '') {
                            socket["chat"] = Clients[i].saved - comments
                        }
                    }
                    console.log('Client joined the chat.');
                    socket.emit('load', 'http://' + ip.address() + ':' + port + '/chats?', socket["chat"]);
                });
            } else {
                lTimestamp = lTimestamp.toString();
                resTimestamp = res.timestamp.toString();
                lTimestamp = lTimestamp.substr(0, resTimestamp.length);

                if (Number(lTimestamp) - 1000 < Number(res.timestamp)) {
                    socket["chat"] = res.data;
                }
                for (i = 0; i < Clients.length; i++) {
                    if (Clients[i].apikey == apikey && Clients[i].saved - comment != 0 && Clients[i].saved - comments != '') {
                        socket["chat"] = Clients[i].saved - comments;
                    }
                }

                console.log('Client joined the chat.');
                socket.emit('load', 'http://' + ip.address() + ':' + port + '/chats?', socket['chat']);
            }

            saveComments = setInterval(() => {
                lTimestamp = Date.now();
                let chat = document.getElementById('chats-box').innerHTML;
                saveChats(apikey, id, chat, () => { });
            }, 8000);

            getChat = setInterval(() => {
                getComments(apikey, id, res => {
                    lTimestamp = lTimestamp.toString();
                    resTimestamp = res.timestamp.toString();
                    lTimestamp = lTimestamp.substr(0, resTimestamp.length);

                    if (Number(lTimestamp) + 1000 < Number(res.timestamp))
                        socket['chat'] = res.data;
                });
            }, 6000);
        });

        app.use(express.urlencoded({ extended: true }));
        app.post('/login', (req, res) => {
            var client;
            int = 0;
            while (i < Users.length) {
                if (Users[i].id == req.body.socketID) {
                    client = Users[i];
                }
                i++;
            }

            if (client != 'undefined') {
                login(req.body.email, req.body.password, req => {
                    if (req.status == 'failed') {
                        client.emit('login-failed');
                    } else {
                        getArticles(req.data);
                        client.emit('login-success');
                    }
                })
            }
        });

    });

    // //helper function to Login through API
    function login(email, password, callback) {
        var data = {
            uri: ApiPath,
            method: 'POST',
            json: {

                "type": "login",
                "email": email,
                "password": password

            }
        };
        request(data, (err, res, body) => {
            if (!err && res.statusCode == 200) {
                callback(res.body);
            }
        });
    };

    // //helper function to retrieve Trakt progress from API
    function getComments(apikey, id, callback) {
        var options = {
            uri: ApiPath,
            method: 'POST',
            json: {
                "key": apikey,
                "type": "chat",
                "id": id
            }
        };
        request(options, (err, res, body) => {
            if (!err && res.statusCode == 200) {
                callback(res.body);
            }
        });
    };

    // //helper function to set Trakt progress from API
    function saveChat(apikey, id, chat, callback) {
        var options = {
            uri: ApiPath,
            method: 'POST',
            json: {
                "key": apikey,
                "type": "chat",
                "id": id,
                "save": chat
            }
        };
        request(options, (err, res, body) => {
            if (!err && res.statusCode == 200) {
                callback(res);
            }
        });
    };




    socket.on('new-user', name => {
        users[socket.id] = name;
        socket.broadcast.emit('user-connected', name);
    });

    socket.on('send-comment', msg => {
        socket.broadcast.emit('send-comment', { comment: msg, name: users[socket.id] });
    });

    socket.on('disconnect', () => {
        clearInterval(saveComments);
        clearInterval(getChat);
        lTimestamp = Date.now();
        console.log("| Saving Chat...");
        saveChat(socket.apikey, socket.id, socket.chat, () => { });

        var i = Users.indexOf(socket);
        Users.splice(i, 1);

        var i = Users.indexOf(socket);
        Clients.splice(i, 1);

        console.log('| User: ' + socket.id + ' disconnected.');
        if (Users.length == 0) {
            console.log("| No users");
            console.log("+ -------------------------------");
        }


        socket.broadcast.emit('user-disconnected', users[socket.id]);
        delete users[socket.id];
    });
});

app.get('/css/style.css', (req, res) => {
    res.sendFile(__dirname + "/client/css/style.css");
});

app.get('/css/registration.css', (req, res) => {
    res.sendFile(__dirname + "/client/css/registration.css");
});

app.get('/img/logo.svg', (req, res) => {
    res.sendFile(__dirname + "/client/img/logo.svg");
});

app.get('/img/launch-bkg.mp4', (req, res) => {
    res.sendFile(__dirname + "/client/img/launch-bkg.mp4");
});

app.get('/js/index.js', (req, res) => {
    res.sendFile(__dirname + "/client/js/index.js");
});

app.get('/', (req, res, next) => {
    res.sendFile('client/index.html', { root: __dirname })
});














































