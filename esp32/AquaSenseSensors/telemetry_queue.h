#pragma once

#include <Arduino.h>

#include "config.h"
#include "telemetry.h"

// Fixed RAM-only FIFO. It deliberately owns no dynamic memory and cannot grow.
// A complete ESP32 reset or loss of power clears every queued record.
class TelemetryQueue {
 public:
  bool enqueue(const PendingTelemetry& record);
  PendingTelemetry* front();
  const PendingTelemetry* front() const;
  bool pop();
  size_t size() const;
  size_t capacity() const;
  size_t highWaterMark() const;
  uint32_t droppedOldestCount() const;

 private:
  PendingTelemetry records_[AquaSenseConfig::TELEMETRY_QUEUE_CAPACITY]{};
  size_t head_ = 0;
  size_t count_ = 0;
  size_t highWaterMark_ = 0;
  uint32_t droppedOldestCount_ = 0;
};

bool telemetryQueueSelfTest();
