zainGmailTest = {};

zainGmailTest.queries = [
    'in:inbox To:shah1936@example.com',
    'in:inbox To:sak555@example.com',
    'in:inbox To:aqil_sajjad@example.ca'
];
zainGmailTest.queryIndex = -1;
zainGmailTest.getInputElement = function () {
    if (!this.searchInput) {
        this.searchInput = document.getElementsByName('q')[0];
    }
    return this.searchInput;
};

zainGmailTest.changeInput = function (newInput) {
    var element = this.getInputElement();
    element.value = newInput;
};

zainGmailTest.getInputValue = function () {
    var element = this.getInputElement();
    return element.value;
};

zainGmailTest.goNext = function () {
    var query = this.getNextQuery();
    if (query) {
        this.changeInput(query);
        // this.simulateKeyPress(this.getInputElement());
        this.clickButton();
    }
};
zainGmailTest.getNextQuery = function () {
    this.queryIndex++;
    return this.queries[this.queryIndex];
};

zainGmailTest.simulateKeyPress = function (element) {
    // var evt = document.createEvent("KeyboardEvent");
    // evt.initKeyEvent("keypress", true, true, window,
    //     0, 0, 0, 0,
    //     0, "e".charCodeAt(0));
    var typeArg = 'keypress';
    var KeyboardEventInit = {key:"Enter"};
    var evt = new KeyboardEvent(typeArg, KeyboardEventInit);
    element.dispatchEvent(evt);
};
zainGmailTest.getForm = function()
{
    var forms = document.getElementsByTagName("form");
    return forms[3];
};
zainGmailTest.clickButton = function () {
    var searchButton  = document.getElementsByTagName("button")[0];
    searchButton.click();
};
zainGmailTest.goNext();