jQuery(document).ready(function($) {
    $('#wc-booking-calendar').fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        events: function(start, end, timezone, callback) {
            // Fetch bookings from the server
            $.ajax({
                url: wc_booking_admin_ajax.ajax_url,
                data: {
                    action: 'wcbs_get_bookings_for_calendar',
                    _nonce: wc_booking_admin_ajax.nonce,
                },
                success: function(data) {
                    var events = data.bookings.map(function(booking) {
                        return {
                            title: booking.customer_name,
                            start: booking.start_date,
                            end: booking.end_date,
                            order_number: booking.order_number,
                            customer_name: booking.customer_name,
                        };
                    });
                    callback(events);
                }
            });
        },
        eventRender: function(event, element) {
            // On hover, show booking details
            element.qtip({
                content: 'Order Number: ' + event.order_number + '<br>' +
                         'Customer: ' + event.customer_name + '<br>' +
                         'End Date: ' + event.end_date,
                style: {
                    classes: 'qtip-bootstrap'
                }
            });
        }
    });
});
