#pragma once

#include <Arduino.h>

void networkBegin();
void networkUpdate(uint32_t nowMs);
bool networkConnected();
void printNetworkStatus();
void networkForceDisconnectForTest();
uint32_t networkConnectionCount();
uint32_t networkReconnectCount();
uint32_t networkDisconnectCount();
