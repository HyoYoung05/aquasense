#include "telemetry_queue.h"

bool TelemetryQueue::enqueue(const PendingTelemetry& record) {
  bool droppedOldest = false;
  if (count_ == capacity()) {
    head_ = (head_ + 1U) % capacity();
    --count_;
    ++droppedOldestCount_;
    droppedOldest = true;
  }
  const size_t tail = (head_ + count_) % capacity();
  records_[tail] = record;
  ++count_;
  if (count_ > highWaterMark_) highWaterMark_ = count_;
  return droppedOldest;
}

PendingTelemetry* TelemetryQueue::front() {
  return count_ == 0U ? nullptr : &records_[head_];
}

const PendingTelemetry* TelemetryQueue::front() const {
  return count_ == 0U ? nullptr : &records_[head_];
}

bool TelemetryQueue::pop() {
  if (count_ == 0U) return false;
  records_[head_] = PendingTelemetry{};
  head_ = (head_ + 1U) % capacity();
  --count_;
  return true;
}

size_t TelemetryQueue::size() const { return count_; }

size_t TelemetryQueue::capacity() const {
  return AquaSenseConfig::TELEMETRY_QUEUE_CAPACITY;
}

size_t TelemetryQueue::highWaterMark() const { return highWaterMark_; }

uint32_t TelemetryQueue::droppedOldestCount() const {
  return droppedOldestCount_;
}

bool telemetryQueueSelfTest() {
  TelemetryQueue queue;
  for (size_t index = 0;
       index < AquaSenseConfig::TELEMETRY_QUEUE_CAPACITY + 1U; ++index) {
    PendingTelemetry record{};
    record.active = true;
    record.queueSequence = static_cast<uint32_t>(index + 1U);
    queue.enqueue(record);
  }
  if (queue.size() != AquaSenseConfig::TELEMETRY_QUEUE_CAPACITY ||
      queue.highWaterMark() != AquaSenseConfig::TELEMETRY_QUEUE_CAPACITY ||
      queue.droppedOldestCount() != 1U || queue.front() == nullptr ||
      queue.front()->queueSequence != 2U) return false;
  uint32_t expected = 2U;
  while (const PendingTelemetry* record = queue.front()) {
    if (record->queueSequence != expected++) return false;
    queue.pop();
  }
  return queue.size() == 0U &&
         expected == AquaSenseConfig::TELEMETRY_QUEUE_CAPACITY + 2U;
}
