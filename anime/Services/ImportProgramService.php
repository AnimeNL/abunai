<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// The import-program service is responsible for downloading the events and rooms in which events
// will be taking place. The format of the input is entirely proprietary to AnimeCon, so an
// intermediate format has been developed to make adoption for other input types easier.
//
// This service has the following configuration options that must be supplied:
//
//     'destination'  File to which the parsed program information should be written in JSON format.
//
//     'frequency'    Frequency at which to execute the service (in minutes). This value should most
//                    likely be adjusted to run more frequently as the event comes closer.
//
//     'source'       Absolute URL to the JSON source document as described below.
//
// A number of things have to be considered when considering this input format:
//
//     (1) The opening and closing of larger events has been split up in two separate entries.
//     (2) Location names are not to be relied upon and may be changedd at moment's notice. Using
//         the locationId as a unique identifier will be more stable.
//
// Because the input data may change from underneath us at any moment, a validation routing has been
// included in this service that the input must pass before it will be considered for importing.
// Failures will raise an exception, because they will need manual consideration.
class ImportProgramService implements Service {
    // Array containing the fields in a program entry that must be present for this importing
    // service to work correctly. The verification step will make sure that they're all present.
    // Marked as public for testing purposes only.
    public const REQUIRED_FIELDS = ['name', 'start', 'end', 'location', 'comment', 'hidden',
                                    'floor', 'eventId', 'tsId', 'opening'];

    private $options;
    private $ignoredTimeSlots;

    // Initializes the service with |$options|, defined in the website's configuration file.
    public function __construct(array $options) {
        if (!array_key_exists('destination', $options))
            throw new \Exception('The ImportProgramService requires a `destination` option.');

        if (!array_key_exists('frequency', $options))
            throw new \Exception('The ImportProgramService requires a `frequency` option.');

        if (!array_key_exists('source', $options))
            throw new \Exception('The ImportProgramService requires a `source` option.');

        $this->options = $options;
        $this->ignoredTimeSlots =
            array_key_exists('ignored_time_slots', $options) ? $options['ignored_time_slots']
                                                             : [];
    }

    // Returns a textual identifier for identifying this service.
    public function getIdentifier() : string {
        return 'import-program-service';
    }

    // Returns the frequency at which the service should run. This is defined in the configuration
    // because we may want to run it more frequently as the event comes closer.
    public function getFrequencyMinutes() : int {
        return $this->options['frequency'];
    }

    // Actually imports the program from the API endpoint defined in the options. The information
    // will be distilled per the class-level documentation block's quirks and written to the
    // destination file in accordance with our own intermediate format.
    public function execute() : void {

        // Load from $this->options['source']

        // Animecon format
        $input = [
            [
                'name'      => 'Nardo Time',
                'start'     => '2018-08-25T15:00:00+01:00',
                'end'       => '2018-08-25T18:00:00+01:00',
                'location'  => 'Jacco\'s Man Cave',
                'comment'   => 'Nardoooooooo',
                'hidden'    => 0,
                'floor'     => 'floor-1',
                'eventId'   => 12345,
                'opening'   => 0 /* event */
            ]
        ];

        // Translate the |$input| data into our own intermediate program format.
        $program = $this->convertToIntermediateProgramFormat($input);

        $programData = json_encode($program);

        // Write the |$programData| to the destination file indicated in the configuration.
        if (file_put_contents($this->options['destination'], $programData) != strlen($programData))
            throw new \Exception('Unable to write the program data to the destination file.');
    }

    // Converts |$entries| to the intermediate event format used by this portal. It basically takes
    // the naming and values of the AnimeCon format and converts it into something more sensible.
    // This method has public visibility for testing purposes only.
    public function convertToIntermediateProgramFormat(array $entries) : array {
        $events = [];

        // Iterate over all entries which have been merged since, storing them in a series of events
        // each of which have one or multiple sessions.
        foreach ($entries as $entry) {
            $session = [
                'name'          => $entry['name'],
                'description'   => $entry['comment'],

                'begin'         => strtotime($entry['start']),
                'end'           => strtotime($entry['end']),

                'location'      => $entry['location'],
                'floor'         => (int) (substr($entry['floor'], 6)),
            ];

            // Coalesce this session with the existing event if it exists.
            if (array_key_exists($entry['eventId'], $events)) {
                $events[$entry['eventId']]['sessions'][] = $session;
                continue;
            }

            $events[$entry['eventId']] = [
                'id'            => $entry['eventId'],
                'hidden'        => !!$entry['hidden'],
                'sessions'      => [ $session ]
            ];
        }

        return array_values($events);
    }
}
