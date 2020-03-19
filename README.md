# NoteTaker
An EM that allows comments to be added to a single notes field, formatted with user and timestamp.
<br>
Notes will be arranged newest to oldest and prepended with a header with the following format : [username @ date]
<br>
##Configuration options
The following instrument fields can be specified:
1. [Arm & Event ID] : The event where the records intended to be affected by this EM on are located
2. [Input Field] : The input instance you would like to pull text from to be added to the notes field
3. [Date Field] : The date field that will be populated anytime a user edits the input field. Formats include date, datetime and datetime_seconds
4. [Notes Field] : The field that contains all input field text separated by a header and optional delimiter
5. [Include a delimiter] : If checked, will separate each valid record update with a string "______________"


##Notes:
When choosing between validation formats for date in the designer, feel free to choose between the following options<br>
1. Date
2. Datetime
3. Datetime w/seconds

Note however that the header will be formatted in (Y-M-D) regardless of your choice. The only difference being the specificity rather than the order.
If specifying an altering order, the data will save in (Y-M-D) format, and you might receive an alert message upon record save.


