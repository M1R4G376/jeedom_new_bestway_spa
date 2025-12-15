#!/usr/bin/env python3

# -*- coding: utf-8 -*-

import argparse

import json

import time

import sys

import traceback

import hashlib

import random

import string

import requests

# ============================================================

#  CONSTANTES APP (issues du SmartHub officiel)

# ============================================================

APPID = "AhFLL54HnChhrxcl9ZUJL6QNfolTIB"

APPSECRET = "4ECvVs13enL5AiYSmscNjvlaisklQDz7vWPCCWXcEFjhWfTmLT"

# ============================================================

#  LOGGING

# ============================================================

def log(msg):

    ts = time.strftime('%Y-%m-%d %H:%M:%S')

    print(f"[{ts}] {msg}")

    sys.stdout.flush()

# ============================================================

#  SIGNATURE BESTWAY

# ============================================================

def generate_auth():

    nonce = ''.join(random.choices(string.ascii_lowercase + string.digits, k=32))

    ts = str(int(time.time()))

    sign = hashlib.md5((APPID + APPSECRET + nonce + ts).encode("utf-8")).hexdigest().upper()

    return nonce, ts, sign

# ============================================================

#  AUTHENTIFICATION BESTWAY

# ============================================================

def authenticate(session, config):

    api_host = config["api_host"]

    base_url = "https://" + api_host

    payload = {

        "app_id": APPID,

        "lan_code": "en",

        "location": config["location"],

        "push_type": config["push_type"],

        "timezone": config["timezone"],

        "visitor_id": config["visitor_id"],

        "registration_id": config["registration_id"],

    }

    if config["push_type"] == "fcm" and config["client_id"]:

        payload["client_id"] = config["client_id"]

    nonce, ts, sign = generate_auth()

    headers = {

        "pushtype": config["push_type"],

        "appid": APPID,

        "nonce": nonce,

        "ts": ts,

        "accept-language": "en",

        "sign": sign,

        "Authorization": "token",

        "Host": api_host,

        "Connection": "Keep-Alive",

        "User-Agent": "okhttp/4.9.0",

        "Content-Type": "application/json; charset=UTF-8",

    }

    log(f"Authenticating to {base_url}/api/enduser/visitor")

    log(f"Payload: {payload}")

    resp = session.post(f"{base_url}/api/enduser/visitor", headers=headers, json=payload, verify=False, timeout=10)

    data = resp.json()

    log(f"Auth response: {data}")

    token = data.get("data", {}).get("token")

    if not token:

        raise RuntimeError("Impossible de récupérer le token Bestway")

    return token

# ============================================================

#  API BESTWAY

# ============================================================

class BestwaySpaAPI:

    def __init__(self, session, config):

        self.session = session

        self.api_host = config["api_host"]

        self.base_url = "https://" + self.api_host

        self.token = config["token"]

        self.device_id = config["device_id"]

        self.product_id = config["product_id"]

        self.push_type = config["push_type"]

    def _headers(self):

        nonce, ts, sign = generate_auth()

        return {

            "pushtype": self.push_type,

            "appid": APPID,

            "nonce": nonce,

            "ts": ts,

            "accept-language": "en",

            "sign": sign,

            "Authorization": f"token {self.token}",

            "Host": self.api_host,

            "Connection": "Keep-Alive",

            "User-Agent": "okhttp/4.9.0",

            "Content-Type": "application/json; charset=UTF-8",

        }

    def get_status(self):

        payload = {"device_id": self.device_id, "product_id": self.product_id}

        log(f"Sending get_status payload: {payload}")

        resp = self.session.post(

            f"{self.base_url}/api/device/thing_shadow/",

            headers=self._headers(),

            json=payload,

            verify=False,

            timeout=10,

        )

        data = resp.json()

        log(f"Full API response: {data}")

        raw = data.get("data", {})

        state = raw.get("state", raw)

        if "reported" in state:

            state = state["reported"]

        elif "desired" in state:

            state = state["desired"]

        mapped = {

            "wifi_version": state.get("wifivertion"),

            "ota_status": state.get("otastatus"),

            "mcu_version": state.get("mcuversion"),

            "trd_version": state.get("trdversion"),

            "connect_type": state.get("ConnectType"),

            "power_state": state.get("power_state"),

            "heater_state": state.get("heater_state"),

            "wave_state": state.get("wave_state"),

            "filter_state": state.get("filter_state"),

            "temperature_setting": state.get("temperature_setting"),

            "temperature_unit": state.get("temperature_unit"),

            "water_temperature": state.get("water_temperature"),

            "warning": state.get("warning"),

            "error_code": state.get("error_code"),

            "hydrojet_state": state.get("hydrojet_state"),

            "is_online": state.get("is_online"),

        }

        log(f"Normalized data: {mapped}")

        return mapped

# ============================================================

#  BRIDGE JEEDOM

# ============================================================

class JeedomBridge:

    def __init__(self, jeedom_ip, apikey):

        self.url = f"http://{jeedom_ip}/plugins/new_bestway_spa/core/php/new_bestway_spa.api.php"

        self.apikey = apikey

    def send_state(self, spa_id, data):

        payload = json.dumps({

            "type": "state",

            "spa_id": spa_id,

            "data": data

        })

        params = {

            "apikey": self.apikey,

            "payload": payload,

        }

        try:

            r = requests.post(self.url, data=params, timeout=5)

            log(f"Envoi état Jeedom: HTTP {r.status_code} - {r.text}")

        except Exception as e:

            log(f"Erreur envoi état Jeedom: {e}")

# ============================================================

#  DEMARRAGE PRINCIPAL

# ============================================================

def main():

    # ----- PARSER ARGUMENTS -----

    parser = argparse.ArgumentParser()

    parser.add_argument("--apikey", required=True)

    parser.add_argument("--jeedom_ip", required=True)

    parser.add_argument("--refresh", type=int, default=30)

    parser.add_argument("--api_host", default="smarthub-eu.bestwaycorp.com")

    parser.add_argument("--visitor_id", required=True)

    parser.add_argument("--client_id", default="")

    parser.add_argument("--registration_id", required=True)

    parser.add_argument("--device_id", required=True)

    parser.add_argument("--product_id", required=True)

    parser.add_argument("--push_type", default="fcm")

    parser.add_argument("--location", default="GB")

    parser.add_argument("--timezone", default="GMT")

    args = parser.parse_args()

    log(f"Démon new_bestway_spa démarré avec jeedom_ip={args.jeedom_ip}")

    # ----- PREPARATION -----

    bridge = JeedomBridge(args.jeedom_ip, args.apikey)

    config = {

        "api_host": args.api_host,

        "visitor_id": args.visitor_id,

        "client_id": args.client_id,

        "registration_id": args.registration_id,

        "device_id": args.device_id,

        "product_id": args.product_id,

        "push_type": args.push_type,

        "location": args.location,

        "timezone": args.timezone,

    }

    session = requests.Session()

    # ----- AUTHENTIFICATION BESTWAY -----

    try:

        token = authenticate(session, config)

        config["token"] = token

    except Exception as e:

        log(f"Erreur d'authentification SmartHub : {e}")

        traceback.print_exc()

        sys.exit(1)

    api = BestwaySpaAPI(session, config)

    # ----- BOUCLE PRINCIPALE -----

    while True:

        try:

            status = api.get_status()

            bridge.send_state(args.device_id, status)

        except Exception as e:

            log(f"Exception boucle principale : {e}")

            traceback.print_exc()

        time.sleep(args.refresh)

# ============================================================

#  LANCEMENT

# ============================================================

if __name__ == "__main__":

    main()