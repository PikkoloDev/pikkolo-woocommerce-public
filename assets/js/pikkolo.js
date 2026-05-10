jQuery(function ($) {
  console.log("Pikkoló SDK init");
  console.log(_pikkolo);

  var pikkolo_handler = {
    click: function (e) {
      e.preventDefault();
      pikkoloShowChooseAnotherStationModal()
        .then((stationAndDeliveryTime) => {
          if (stationAndDeliveryTime) {
            const { station, deliveryTime } = stationAndDeliveryTime;
            setChosenPikkoloStation(station, deliveryTime);
          } else {
            $("#pikkolo_chosen_station").text(_pikkolo.i18n.noStationAvailable);
          }
        })
        .catch((error) => {
          console.log("Error from SDK", error);
          $("#pikkolo_choose_another").hide();
        });
    },
    init: function () {
      var existingStationId = Cookies.get("pikkolo_station_id");
      var existingStationName = Cookies.get("pikkolo_station_name");

      if (existingStationId && existingStationName) {
        $("#pikkolo_chosen_station").html(
          existingStationName +
            " (<a target='_blank' href='#'>" +
            _pikkolo.i18n.seeOnMap +
            "</a>).",
        );
        $("#pikkolo_choose_another").show().text(_pikkolo.i18n.chooseStation);
      } else {
        $("#pikkolo_chosen_station").text(_pikkolo.i18n.noChosenStation);
        $("#pikkolo_choose_another").show().text(_pikkolo.i18n.chooseStation);
      }

      $("#pikkolo_station_error").text("").hide();

      const currentDate = new Date();
      const futureDate = new Date();

      currentDate.setDate(currentDate.getDate());
      futureDate.setDate(futureDate.getDate() + 7);

      $("#pikkolo_choose_another").on("click", pikkolo_handler.click);

      pikkoloGetAvailableStations({
        cartContents: {
          nrOfRefrigeratedItems: 1,
          nrOfFrozenItems: 0,
        },
        countryCode: _pikkolo.country ? _pikkolo.country : "IS",
        city: _pikkolo.city ? _pikkolo.city : "Reykjavík",
        postalCode: _pikkolo.postcode ? _pikkolo.postcode : "101",
        street: _pikkolo.address,
        phoneNumberHash: pikkoloHashPhoneNumber(_pikkolo.phone),
        emailHash: pikkoloHashEmail(_pikkolo.email),
        startDate: currentDate,
        endDate: futureDate,
      })
        .then(function ({ bestAvailableStation, stations }) {
          console.log("Response from SDK", bestAvailableStation, stations);
          if (bestAvailableStation) {
            if (!Cookies.get("pikkolo_station_id")) {
              $("#pikkolo_chosen_station").text(_pikkolo.i18n.noChosenStation);
            }
            $("#pikkolo_choose_another").show();
            $("#pikkolo_choose_another").text(_pikkolo.i18n.chooseStation);
          } else {
            $("#pikkolo_choose_another").hide();
            $("#pikkolo_chosen_station").text(_pikkolo.i18n.noStationAvailable);
            $("#pikkolo").attr("disabled", true);
          }
        })
        .catch(function (error) {
          console.error("Error from SDK", error);
          $("#pikkolo_choose_another").hide();
          $("#pikkolo_chosen_station").text(_pikkolo.i18n.noStationAvailable);
          $("#pikkolo").attr("disabled", true);
        });
    },
  };

  function setChosenPikkoloStation(station, deliveryTime) {
    $("#pikkolo_chosen_station").html(
      station.name +
        " (<a target='_blank' href='" +
        station.googleMapsLink +
        "'>" +
        _pikkolo.i18n.seeOnMap +
        "</a>).",
    );
    $("#pikkolo_station_error").text("").hide();

    Cookies.set("pikkolo_station_id", station.id, { path: "/" });
    Cookies.set("pikkolo_station_name", station.name, { path: "/" });
    Cookies.set("pikkolo_delivery_time_id", deliveryTime.id, { path: "/" });
  }

  $(document.body).on("click", "#place_order", function (e) {
    var isPikkoloChosen = $('input[name="shipping_method[0]"]:checked').val();
    if (isPikkoloChosen && isPikkoloChosen.indexOf("pikkolois") !== -1) {
      if (
        !Cookies.get("pikkolo_station_id") ||
        !Cookies.get("pikkolo_delivery_time_id")
      ) {
        e.preventDefault();
        e.stopImmediatePropagation();
        var errorMsg = _pikkolo.i18n.mustChooseStation;
        $("#pikkolo_station_error").text(errorMsg).show();
        $("html, body").animate(
          { scrollTop: $("#pikkolo_station_error").offset().top - 100 },
          400,
        );
        return false;
      }
    }
  });

  pikkolo_handler.init();

  $(document).on("updated_shipping_method", pikkolo_handler.init); // Updated shipping method
  $(document).on("updated_checkout", pikkolo_handler.init); // Updated checkout
});
