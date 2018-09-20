# CustomRecentChanges

CustomRecentChanges redesigns the ``Special:RecentChanges`` page to make it easier to read.

## Installation

* Download the full archive of this repository
* Extract and put ``CustomRecentChanges`` folder in the ``extensions`` directory.
* Add the following line to the ``LocalSettings.php``
```
wfLoadExtension('CustomRecentChanges');
```

## Usage
The recent changes page is located at ``Special:DokitRecentChanges``

## Configuration

By default, it will display 5 namespaces

* Page (0)
* User (2)
* File (6)
* Category (14)
* Group (220)

But you can add namespaces as many as you want by editing the following configurations variables

### ``$wgRCNamespacesList`` 
(array) Array of integers. In the filters, only display the specified namespaces.

Exemple :
```
<?php
    # LocalSettings.php 
    
    $wgRCNamespacesList = [
        0, // User
        2, // File
        14 // Category
    ];
?>
```

### ``$wgRCNamespacesListIgnored`` 
(array) Array of integers. In the filters, display all the namespaces but the ignored.

Exemple :
```
<?php
    # LocalSettings.php 
    
    // Will ignore the File namespace
    $wgRCNamespacesListIgnored = [
        2, // File
    ];
?>
```

## Translation

As usual, the translation files are located in the ``i18n`` folder. You can translate / change the namespace buttons on the filter bar. Just add a translation to a file. 

The key for translating a namespace button begins with ``customrecentchanges-namespace-`` and ends by the english or official namespace name in lowercase.

Exemple of a french  for the ``Group`` namespace: 
```
// fr.json
{
    "customrecentchanges-namespace-group": "Groupes"
}
```
